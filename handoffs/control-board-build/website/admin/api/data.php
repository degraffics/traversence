<?php
// admin/api/data.php
//
// Admin Data Browser endpoint: lists, searches, filters, sorts and edits rows
// of ANY table, driven by information_schema (no per-table code).
//
//   GET  ?action=tables
//   GET  ?action=meta&table=T
//   GET  ?action=rows&table=T&q=&sort=&dir=&page=&f[col]=value
//   GET  ?action=row&table=T&id=N
//   POST {action:'update', table, id, changes:{col:val}, before:{col:val}}
//        (X-CSRF-Token header required)
//
// Access: Auth::requireAdmin() (users.is_admin). The uploaded Auth.php has no
// super-admin / hub-scoped check, so ANY admin can edit ANY hub here. Tighten
// the gate below once Auth gains requireSuperAdmin().
//
// Every edit is written to admin_edit_log in the same transaction. If the log
// table is missing the edit is refused (fails closed). See admin_edit_log.sql.

declare(strict_types=1);

require_once __DIR__ . '/../../../app/bootstrap.php';

$adminUser = Auth::requireAdmin();   // <-- tighten here later (super admin / hub scope)

// ---- Policy (edit these lists, not the logic) ----------------------------
const DB_HIDDEN_TABLES   = ['email_confirmations'];
const DB_READONLY_TABLES = ['users', 'admin_access', 'listing_access', 'connections', 'admin_edit_log'];
const DB_HIDDEN_COL_RE   = '/password|secret|token/i';   // never sent, never editable
const DB_READONLY_COL_RE = '/hash/i';                    // shown, never editable (e.g. dedup hash)
const DB_LOG_TABLE       = 'admin_edit_log';
const DB_PER_PAGE        = 25;

$pdo    = Database::connection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---- Schema helpers -------------------------------------------------------
function dbTables(PDO $pdo): array
{
    // every realm's tables (the control board's build, D7: it listed the main database only): main's, and each table moved
    // to another realm (data_moves, decisions/0110), named as before and read through its own database
    $out = [];
    foreach (dbRealmTables($pdo) as $name => [$approx]) $out[$name] = $approx;
    return $out;
}

/** name => [approx rows, realm ('main' or the realm it moved to)], hidden tables left out. */
function dbRealmTables(PDO $pdo): array
{
    static $memo = null;
    if ($memo !== null) return $memo;
    $memo = [];
    $st = $pdo->query(
        "SELECT TABLE_NAME AS name, TABLE_ROWS AS approx
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
          ORDER BY TABLE_NAME"
    );
    foreach ($st->fetchAll() as $r) {
        if (in_array(strtolower((string) $r['name']), DB_HIDDEN_TABLES, true)) continue;
        $memo[(string) $r['name']] = [(int) $r['approx'], 'main'];
    }
    foreach (Database::moves() as $tbl => $realm) {
        if (in_array(strtolower((string) $tbl), DB_HIDDEN_TABLES, true)) continue;
        try {
            $q = $pdo->prepare("SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t AND TABLE_TYPE = 'BASE TABLE'");
            $q->execute(['s' => Database::schemaOf((string) $tbl), 't' => (string) $tbl]);
            $n = $q->fetchColumn();
            if ($n !== false) $memo[(string) $tbl] = [(int) $n, (string) $realm];   // the admin login can see it
        } catch (Throwable $e) {}
    }
    ksort($memo);
    return $memo;
}

/** A table's name for SQL, with its database once it has moved (Database::t). */
function dbT(string $table): string
{
    return Database::t($table);
}

function dbRequireTable(PDO $pdo, string $table): void
{
    if (!array_key_exists($table, dbTables($pdo))) {
        Response::error('Unknown table.', 404, 'not_found');
    }
}

/** Column metadata for a table (hidden columns already removed). */
function dbColumns(PDO $pdo, string $table): array
{
    $st = $pdo->prepare(
        'SELECT COLUMN_NAME AS name, DATA_TYPE AS dtype, COLUMN_TYPE AS ctype,
                IS_NULLABLE AS nullable, COLUMN_KEY AS ckey, EXTRA AS extra
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t
          ORDER BY ORDINAL_POSITION'
    );
    $st->execute(['s' => Database::schemaOf($table), 't' => $table]);
    $rows = $st->fetchAll();

    $fk = $pdo->prepare(
        'SELECT COLUMN_NAME AS col, REFERENCED_TABLE_NAME AS ref
           FROM information_schema.KEY_COLUMN_USAGE
          WHERE TABLE_SCHEMA = :s AND TABLE_NAME = :t
            AND REFERENCED_TABLE_NAME IS NOT NULL'
    );
    $fk->execute(['s' => Database::schemaOf($table), 't' => $table]);
    $fks = [];
    foreach ($fk->fetchAll() as $r) {
        $fks[(string) $r['col']] = (string) $r['ref'];
    }

    $pkCount = 0;
    foreach ($rows as $r) {
        if ($r['ckey'] === 'PRI') {
            $pkCount++;
        }
    }

    $cols = [];
    foreach ($rows as $r) {
        $name = (string) $r['name'];
        if (preg_match(DB_HIDDEN_COL_RE, $name)) {
            continue;
        }
        $dtype = strtolower((string) $r['dtype']);
        $ctype = (string) $r['ctype'];
        $extra = strtolower((string) $r['extra']);

        $options = null;
        if ($dtype === 'enum') {
            preg_match_all("/'((?:[^']|'')*)'/", $ctype, $m);
            $options = array_map(static fn ($v) => str_replace("''", "'", $v), $m[1]);
        }

        if ($dtype === 'enum') {
            $kind = 'enum';
        } elseif (strtolower($ctype) === 'tinyint(1)') {
            $kind = 'bool';
        } elseif (in_array($dtype, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'year'], true)) {
            $kind = 'int';
        } elseif (in_array($dtype, ['decimal', 'float', 'double'], true)) {
            $kind = 'decimal';
        } elseif ($dtype === 'json') {
            $kind = 'json';
        } elseif (in_array($dtype, ['text', 'tinytext', 'mediumtext', 'longtext'], true)) {
            $kind = 'text';
        } elseif (in_array($dtype, ['char', 'varchar'], true)) {
            $kind = 'string';
        } elseif (in_array($dtype, ['date', 'datetime', 'timestamp', 'time'], true)) {
            $kind = 'datetime';
        } elseif (in_array($dtype, ['blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary', 'geometry', 'point'], true)) {
            $kind = 'binary';
        } else {
            $kind = 'other';
        }

        $isPk      = ($r['ckey'] === 'PRI') && $pkCount === 1;
        $generated = str_contains($extra, 'generated') || str_contains($extra, 'on update');
        $readonly  = $isPk || $generated || $kind === 'binary' || (bool) preg_match(DB_READONLY_COL_RE, $name)
                     || (($r['ckey'] === 'PRI') && $pkCount > 1);

        $cols[] = [
            'name'     => $name,
            'kind'     => $kind,
            'type'     => $ctype,
            'nullable' => $r['nullable'] === 'YES',
            'pk'       => $isPk,
            'auto'     => str_contains($extra, 'auto_increment'),
            'readonly' => $readonly,
            'options'  => $options,
            'fk'       => $fks[$name] ?? null,
        ];
    }
    return $cols;
}

function dbColByName(array $cols, string $name): ?array
{
    foreach ($cols as $c) {
        if ($c['name'] === $name) {
            return $c;
        }
    }
    return null;
}

function dbPk(array $cols): ?string
{
    foreach ($cols as $c) {
        if ($c['pk']) {
            return $c['name'];
        }
    }
    return null;
}

function dbPick(array $cols, array $prefer): ?string
{
    $byLower = [];
    foreach ($cols as $c) {
        $byLower[strtolower($c['name'])] = $c['name'];
    }
    foreach ($prefer as $p) {
        if (isset($byLower[$p])) {
            return $byLower[$p];
        }
    }
    return null;
}

function dbDisplay(array $cols): array
{
    $label = dbPick($cols, ['name', 'title', 'business_name', 'display_name', 'label', 'headline', 'slug', 'email']);
    if ($label === null) {
        foreach ($cols as $c) {
            if ($c['kind'] === 'string' && !$c['pk']) {
                $label = $c['name'];
                break;
            }
        }
    }
    return [
        'label'   => $label,
        'status'  => dbPick($cols, ['status', 'state', 'verification_status', 'is_published', 'published', 'is_active', 'is_approved', 'approved', 'is_suspended']),
        'updated' => dbPick($cols, ['updated_at', 'modified_at', 'created_at']),
    ];
}

function dbQ(string $ident): string
{
    return '`' . str_replace('`', '``', $ident) . '`';
}

function dbOut(array $payload): void
{
    // Response::success() is used as session.php uses it; keep the payload flat.
    Response::success($payload);
}

// ---- Actions --------------------------------------------------------------
if ($method === 'GET') {
    $action = (string) ($_GET['action'] ?? '');

    if ($action === 'tables') {
        $list = [];
        foreach (dbRealmTables($pdo) as $name => [$approx, $realm]) {
            $list[] = ['name' => $name, 'approx' => $approx, 'readonly' => in_array(strtolower($name), DB_READONLY_TABLES, true), 'realm' => $realm,
                       'label' => $realm === 'main' ? $name : $name . ' (' . $realm . ' database)'];
        }
        dbOut(['tables' => $list]);
    }

    $table = (string) ($_GET['table'] ?? '');
    dbRequireTable($pdo, $table);
    $cols = dbColumns($pdo, $table);
    $pk   = dbPk($cols);
    $disp = dbDisplay($cols);
    $tableReadonly = in_array(strtolower($table), DB_READONLY_TABLES, true) || $pk === null;

    if ($action === 'meta') {
        // Filterable columns: enums, booleans, foreign keys, and the status column.
        $filters = [];
        foreach ($cols as $c) {
            $isStatus = ($c['name'] === $disp['status']);
            if (!($c['kind'] === 'enum' || $c['kind'] === 'bool' || $c['fk'] !== null || $isStatus)) {
                continue;
            }
            if ($c['kind'] === 'text' || $c['kind'] === 'json' || $c['kind'] === 'binary') {
                continue;
            }
            if ($c['kind'] === 'enum') {
                $opts = $c['options'];
            } elseif ($c['kind'] === 'bool') {
                $opts = ['0', '1'];
            } else {
                $st = $pdo->query('SELECT DISTINCT ' . dbQ($c['name']) . ' AS v FROM ' . dbT($table)
                    . ' WHERE ' . dbQ($c['name']) . ' IS NOT NULL ORDER BY 1 LIMIT 51');
                $vals = array_map(static fn ($r) => (string) $r['v'], $st->fetchAll());
                $opts = count($vals) <= 50 ? $vals : null;   // null = free-text exact match
            }
            $filters[] = ['col' => $c['name'], 'options' => $opts];
        }
        dbOut([
            'columns'  => $cols,
            'pk'       => $pk,
            'display'  => $disp,
            'filters'  => $filters,
            'readonly' => $tableReadonly,
            'per_page' => DB_PER_PAGE,
        ]);
    }

    if ($action === 'rows') {
        $where  = [];
        $params = [];

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $ors = [];
            $i   = 0;
            foreach ($cols as $c) {
                if (in_array($c['kind'], ['string', 'text', 'enum'], true) && $i < 12) {
                    $ors[]          = dbQ($c['name']) . ' LIKE :q' . $i;
                    $params[':q' . $i] = '%' . addcslashes($q, '%_\\') . '%';
                    $i++;
                }
            }
            if ($pk !== null && ctype_digit($q)) {
                $ors[]            = dbQ($pk) . ' = :qid';
                $params[':qid']   = $q;
            }
            if ($ors) {
                $where[] = '(' . implode(' OR ', $ors) . ')';
            }
        }

        $f = $_GET['f'] ?? [];
        if (is_array($f)) {
            $n = 0;
            foreach ($f as $col => $val) {
                $c = dbColByName($cols, (string) $col);
                if ($c === null || !is_string($val) || $val === '') {
                    continue;
                }
                if ($val === '__null__') {
                    $where[] = dbQ($c['name']) . ' IS NULL';
                } else {
                    $where[]            = dbQ($c['name']) . ' = :f' . $n;
                    $params[':f' . $n]  = $val;
                    $n++;
                }
            }
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $sortCol = dbColByName($cols, (string) ($_GET['sort'] ?? ''));
        $dir     = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $order   = '';
        if ($sortCol !== null && $sortCol['kind'] !== 'binary') {
            $order = ' ORDER BY ' . dbQ($sortCol['name']) . ' ' . $dir . ($pk !== null && $sortCol['name'] !== $pk ? ', ' . dbQ($pk) . ' DESC' : '');
        } elseif ($pk !== null) {
            $order = ' ORDER BY ' . dbQ($pk) . ' DESC';
        }

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $offset = ($page - 1) * DB_PER_PAGE;

        $sel = [
            ($pk !== null ? dbQ($pk) : 'NULL') . ' AS _pk',
            ($disp['label'] !== null ? 'LEFT(' . dbQ($disp['label']) . ', 120)' : 'NULL') . ' AS _label',
            ($disp['status'] !== null ? dbQ($disp['status']) : 'NULL') . ' AS _status',
            ($disp['updated'] !== null ? dbQ($disp['updated']) : 'NULL') . ' AS _updated',
        ];

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM ' . dbT($table) . $whereSql);
        $cnt->execute($params);
        $total = (int) $cnt->fetchColumn();

        $st = $pdo->prepare('SELECT ' . implode(', ', $sel) . ' FROM ' . dbT($table) . $whereSql . $order
            . ' LIMIT ' . DB_PER_PAGE . ' OFFSET ' . (int) $offset);
        $st->execute($params);

        dbOut(['rows' => $st->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => DB_PER_PAGE]);
    }

    if ($action === 'row') {
        if ($pk === null) {
            Response::error('This table has no single-column primary key.', 422, 'no_pk');
        }
        $names = [];
        foreach ($cols as $c) {
            if ($c['kind'] !== 'binary') {
                $names[] = dbQ($c['name']);
            }
        }
        $st = $pdo->prepare('SELECT ' . implode(', ', $names) . ' FROM ' . dbT($table) . ' WHERE ' . dbQ($pk) . ' = :id LIMIT 1');
        $st->execute(['id' => (string) ($_GET['id'] ?? '')]);
        $row = $st->fetch();
        if ($row === false) {
            Response::error('Row not found.', 404, 'not_found');
        }
        dbOut(['row' => $row, 'columns' => $cols, 'pk' => $pk, 'readonly' => $tableReadonly]);
    }

    Response::error('Unknown action.', 400, 'bad_request');
}

if ($method === 'POST') {
    $sent = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($sent === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $sent)) {
        Response::error('Invalid or missing CSRF token.', 403, 'csrf');
    }

    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body) || ($body['action'] ?? '') !== 'update') {
        Response::error('Unknown action.', 400, 'bad_request');
    }

    $table = (string) ($body['table'] ?? '');
    dbRequireTable($pdo, $table);
    $cols = dbColumns($pdo, $table);
    $pk   = dbPk($cols);
    if ($pk === null || in_array(strtolower($table), DB_READONLY_TABLES, true)) {
        Response::error('This table is read-only here.', 403, 'readonly');
    }

    $id      = (string) ($body['id'] ?? '');
    $changes = $body['changes'] ?? null;
    $before  = is_array($body['before'] ?? null) ? $body['before'] : [];
    if ($id === '' || !is_array($changes) || !$changes) {
        Response::error('Nothing to save.', 422, 'no_changes');
    }

    // Validate every changed column against the live schema.
    $clean = [];
    foreach ($changes as $name => $val) {
        $c = dbColByName($cols, (string) $name);
        if ($c === null || $c['readonly']) {
            Response::error("Column '{$name}' cannot be edited.", 422, 'bad_column');
        }
        if ($val === null) {
            if (!$c['nullable']) {
                Response::error("'{$name}' cannot be empty (NULL).", 422, 'not_nullable');
            }
            $clean[$c['name']] = null;
            continue;
        }
        if (!is_scalar($val)) {
            Response::error("Invalid value for '{$name}'.", 422, 'bad_value');
        }
        $val = (string) $val;
        switch ($c['kind']) {
            case 'bool':
                $v = strtolower($val);
                if (!in_array($v, ['0', '1', 'true', 'false'], true)) {
                    Response::error("'{$name}' must be 0 or 1.", 422, 'bad_value');
                }
                $val = ($v === '1' || $v === 'true') ? '1' : '0';
                break;
            case 'int':
                if (!preg_match('/^-?\d+$/', $val)) {
                    Response::error("'{$name}' must be a whole number.", 422, 'bad_value');
                }
                break;
            case 'decimal':
                if (!is_numeric($val)) {
                    Response::error("'{$name}' must be a number.", 422, 'bad_value');
                }
                break;
            case 'enum':
                if (!in_array($val, $c['options'] ?? [], true)) {
                    Response::error("'{$name}' must be one of: " . implode(', ', $c['options'] ?? []) . '.', 422, 'bad_value');
                }
                break;
            case 'json':
                json_decode($val);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Response::error("'{$name}' is not valid JSON.", 422, 'bad_value');
                }
                break;
            default:
                if ($val === '' && !$c['nullable'] && in_array($c['kind'], ['datetime', 'other'], true)) {
                    Response::error("'{$name}' cannot be empty.", 422, 'bad_value');
                }
        }
        $clean[$c['name']] = $val;
    }

    try {
        $result = Database::transaction(function (PDO $pdo) use ($table, $pk, $id, $clean, $before, $adminUser): array {
            $names = array_map('dbQ', array_keys($clean));
            $st = $pdo->prepare('SELECT ' . implode(', ', $names) . ' FROM ' . dbT($table) . ' WHERE ' . dbQ($pk) . ' = :id FOR UPDATE');
            $st->execute(['id' => $id]);
            $cur = $st->fetch();
            if ($cur === false) {
                return ['missing' => true];
            }

            // Optimistic check: refuse if someone changed these fields since the editor opened.
            $conflicts = [];
            foreach ($clean as $col => $_) {
                if (array_key_exists($col, $before)) {
                    $a = $cur[$col] === null ? null : (string) $cur[$col];
                    $b = $before[$col] === null ? null : (string) $before[$col];
                    if ($a !== $b) {
                        $conflicts[] = $col;
                    }
                }
            }
            if ($conflicts) {
                return ['conflicts' => $conflicts];
            }

            $sets   = [];
            $params = ['id' => $id];
            $diff   = [];
            $i      = 0;
            foreach ($clean as $col => $val) {
                $sets[]          = dbQ($col) . ' = :v' . $i;
                $params['v' . $i] = $val;
                $diff[$col]      = [
                    'from' => $cur[$col] === null ? null : mb_substr((string) $cur[$col], 0, 2000),
                    'to'   => $val === null ? null : mb_substr($val, 0, 2000),
                ];
                $i++;
            }
            $up = $pdo->prepare('UPDATE ' . dbT($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . dbQ($pk) . ' = :id');
            $up->execute($params);

            $log = $pdo->prepare(
                'INSERT INTO ' . Database::t(DB_LOG_TABLE) . ' (user_id, table_name, row_pk, changes, created_at)
                 VALUES (:u, :t, :r, :c, NOW())'
            );
            $log->execute([
                'u' => (int) $adminUser['id'],
                't' => $table,
                'r' => $id,
                'c' => json_encode($diff, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            ]);
            return ['ok' => true];
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02' && stripos($e->getMessage(), DB_LOG_TABLE) !== false) {
            Response::error('Edit log table is missing, so nothing was saved. Run admin_edit_log.sql first.', 500, 'log_missing');
        }
        error_log('admin data.php update failed: ' . $e->getMessage());
        Response::error('The database rejected the change: ' . ($e->errorInfo[2] ?? 'unknown error'), 422, 'db_rejected');
    }

    if (!empty($result['missing'])) {
        Response::error('Row not found.', 404, 'not_found');
    }
    if (!empty($result['conflicts'])) {
        Response::error('Changed by someone else since you opened it: ' . implode(', ', $result['conflicts']) . '. Reopen the row and try again.', 409, 'conflict');
    }
    dbOut(['saved' => true]);
}

Response::error('Method not allowed.', 405, 'method_not_allowed');
