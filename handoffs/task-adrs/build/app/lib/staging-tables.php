<?php
// app/lib/staging-tables.php — the one register of tables for staging (decisions/0165 §3): every table's class, and the
// links between rows (which column, JSON path, "kind:id" string or link in text holds a record number of which table).
// A migration or ensure() that makes a table names it here in the same release; the zip check (R14) blocks one that
// doesn't. An unlisted table is treated as *follows live*: copied from live, never carried by Publish, and listed on the
// Staging page as "no class", so nothing staging did in it is lost without being shown.
//
// The classes:
//   records  what people and staff make (listings, places, pages, guides, posts, workflows, settings): live's changes come
//            in by catch-up; staging's are merged in at Publish (StagingPublish)
//   money    orders, payments, invoices, subscriptions, gateway rows: live's come in (stripped); never carried
//   follows  bulk reference data and anything a job recomputes: copied from live; never carried
//   own      staging's own (the held ledger, its logs, sessions, views and taps from staging visits): kept; never carried
//   never    sign-in secrets, devices, the vault, one-time codes, page cache, live's release record: empty on staging
declare(strict_types=1);

final class StagingTables
{
    public const CLASSES = [
        'records' => ['Records', 'What people and staff make: catch-up brings live\'s changes in; Publish merges staging\'s into live.'],
        'money'   => ['Money', 'Orders, payments and the like: live\'s come in, stripped; never carried (everything on staging is sandbox).'],
        'follows' => ['Follows live', 'Reference data and what a job recomputes: copied from live; never carried (live\'s own jobs redo it).'],
        'own'     => ['Staging\'s own', 'Staging\'s ledger, logs, views and taps: kept on staging; never carried.'],
        'never'   => ['Never copied', 'Sign-in secrets, devices, the vault, one-time codes, caches: empty on staging; never carried.'],
    ];

    /** Records: carried. */
    public const RECORDS = [
        // listings and places
        'entities', 'entity_metadata', 'listing_categories', 'listing_access', 'listing_memberships', 'listing_actions', 'categories', 'category_also', 'category_terms', 'category_changes',
        'places', 'place_profile', 'place_guides', 'place_stories', 'place_dossiers', 'landmarks', 'experiences', 'experience_links', 'storefronts', 'business_domains', 'short_codes',
        'hubs', 'geo_hubs', 'hub_counties', 'micro_clusters', 'cluster_overrides', 'cluster_subgroups', 'nations', 'nation_decision_log', 'jurisdictions', 'jurisdiction_laws',
        'identity_facts', 'identity_fact_sources', 'identity_applied', 'identity_pairs', 'hub_stages', 'authority_sources', 'stories', 'live_events', 'live_event_places', 'heritage_places', 'heritage_facts', 'heritage_sources',
        // guides, journeys, topics
        'resource_guides', 'resource_entries', 'how_guides', 'guide_picks', 'guide_requests', 'guide_audits', 'journeys', 'journey_media', 'journey_coauthors', 'suggested_journeys',
        'topics', 'topic_names', 'topic_places', 'topic_relations', 'topic_facts', 'situations', 'situation_needs', 'situation_sources', 'situation_triggers',
        // community and posts
        'community_groups', 'community_group_members', 'community_group_places', 'community_group_tags', 'community_charters', 'community_chapter_offers', 'community_posts', 'community_help',
        'member_posts', 'member_post_media', 'member_post_to', 'member_post_edits', 'content_comments', 'journey_comments', 'content_tags', 'content_reports', 'takedowns',
        'member_profiles', 'user_settings', 'notice_prefs', 'comms_prefs', 'comms_quiet' /* a person's Communications setting (decisions/0172) */, 'pulse_flags', 'pulse_tags' /* a person's own marks in Pulse (0172 step 3) */, 'comms_mail_blocks' /* senders a person blocked in Pulse (0172 §4.7) */, 'pulse_forwards' /* what a person passed on in Pulse (0172) */, 'pulse_views' /* a person's saved views in Pulse (table-standard, 2026-11-20) */, 'comms_routing', 'comms_overseers', 'comms_controls' /* Communications' setup (0167) */, 'user_places', 'user_links', 'people_groups', 'people_group_members', 'people_connections',
        // the site's pages, content and settings
        'site_pages', 'site_block_versions', 'site_block_zones', 'site_block_schedule', 'content_templates', 'content_template_versions', 'hero_media', 'consent_wordings', 'policies',
        'feature_switches', 'automation_switches', 'service_stages' /* each outside service's stage changes (decisions/0173) */, 'service_proposals' /* services someone would like added (decisions/0173 §2) */, 'commerce_settings', 'market_settings', 'cleanup_settings', 'pulse_settings', 'staff_settings', 'outside_requests' /* requests waiting on outside companies (OutsideServices) */, 'search_terms', 'search_meanings', 'search_senses',
        'source_rules', 'sources', 'crawler_sources', 'reference_sources', 'crawl_workflows', 'crawl_capture_tasks',
        // workflows, Pulse, staff and access
        'wf_workflows', 'wf_versions', 'wf_items', 'wf_history', 'workflow_versions', 'pulse_marks', 'pulse_notes', 'pulse_posts', 'admin_permissions', 'admin_access',
        'role_grants', 'role_perms', 'staff_accounts', 'system_tags', 'tags',
        // the business side (not money): CRM, sales, products, gallery
        'crm_customers', 'crm_tags', 'crm_customer_tags', 'crm_notes', 'prospects', 'prospect_log', 'sales_cards', 'sales_territories', 'products', 'price_lists', 'price_list_rules',
        'plan_features', 'plans', 'tax_rates', 'ship_rules', 'cost_items', 'cost_recipes', 'market_items', 'market_item_photos', 'market_item_versions', 'market_posts', 'market_photos',
        'gallery_albums', 'gallery_album_items', 'gallery_album_opts', 'gallery_album_people', 'gallery_items', 'gallery_files', 'gallery_versions', 'storage_folders', 'stock_slots', 'contact_cards',
        'trips', 'trip_stops' /* a member's trips (decisions/0174) */, 'personal_guides' /* a trip published as a Personal Guide (0174 §1) */,
        'address_contacts', 'business_broadcasts', 'collateral', 'biz_codes', 'promotions', 'listing_claims', 'listing_invites', 'people_invites', 'contributors', 'users',
    ];

    /** Money: never carried. */
    public const MONEY = ['orders', 'order_lines', 'order_line_parts', 'order_line_costs', 'order_addresses', 'order_events', 'order_ledger', 'order_files', 'order_proofs', 'order_packs',
        'order_pack_items', 'order_lots', 'order_projects', 'order_feedback', 'order_inbox', 'order_work_orders', 'order_work_order_log', 'order_vendor_bills' /* the vendor's bills on an order (M1b) */, 'order_refund_requests' /* a refund waiting for Jason's OK (M2, decisions/0178 §1) */, 'order_payment_cancels' /* a payment's record cancelled (M2) */, 'qb_settings_log' /* QuickBooks settings' History (M1b) */, 'market_orders', 'market_offers', 'market_offer_events',
        'market_pay_events', 'market_checkout', 'market_cart', 'market_tickets', 'market_downloads', 'market_gateways', 'subscriptions', 'subscription_addons', 'subscription_charges',
        'member_plans', 'biz_payments', 'biz_quotes', 'biz_quote_lines', 'biz_quote_log', 'biz_code_uses', 'promotion_uses', 'transfers', 'disputes', 'qb_connection', 'qb_settings',
        'qbo_items', 'cart_plans', 'vendor_orders', 'vendor_jobs', 'member_post_spend', 'member_storage_earned', 'earned_gb',
        'account_domains', 'domain_events', 'domain_cart', 'wallet_cards', 'wallet_assignments'];   // domains as a product and the wallet (decisions/0169 §1a, 0166 §6a): bought and paid with money, never carried

    /** Follows live: never carried. Names, then patterns. */
    public const FOLLOWS = ['control_ledger', 'control_records' /* the System Control Board's ledger (decisions/0171 §4): the workers call live only */, 'identity_merges', 'engine_steps', 'open_loads', 'zip_coordinates', 'tv_zip_population', 'open_places', 'open_place_asks', 'npi_orgs', 'npi_loads', 'irs_orgs', 'irs_loads', 'rec_places', 'rec_loads', 'landmark_loads',
        'nation_shapes', 'nation_sensitive', 'ip_eu_ranges', 'ip_eu_ranges_new', 'ip_eu_meta', 'search_index', 'search_index_state', 'search_learned', 'search_demand', 'search_counts',
        'search_outcomes', 'search_opens', 'search_suggestion_flags', 'search_frames', 'search_edits', 'search_drafts', 'listing_confidence', 'lead_scores', 'platform_counts', 'tag_counts',
        'content_like_tally', 'crawl_jobs', 'crawl_targets', 'crawl_pages', 'crawl_runs', 'crawl_run_items', 'crawl_requests', 'crawl_costs', 'crawl_service_calls', 'crawl_osm_asks',
        'crawler_raw_index', 'source_facts', 'identity_jobs', 'town_crawl_jobs', 'guide_finds', 'travel_legs', 'osm_usage', 'stock_images', 'place_photos', 'stock_search_cache', 'stock_uses', 'weather',
        'service_issues', 'service_servers', 'ai_jobs', 'video_jobs', 'cleanup_checks', 'cleanup_runs', 'cleanup_ledger', 'data_moves', 'db_updates_log', 'job_runs', 'job_setup',
        'daily_reports', 'situation_gaps', 'match_names', 'junk_name', 'store_junk', 'store_guard', 'store_cite', 'imported', 'crm_sync', 'email_events', 'email_sends', 'email_state',
        'email_suppress', 'email_pauses', 'notices_sent', 'notice_delivery', 'audit_log', 'staff_log', 'role_log', 'automation_log', 'member_access_log', 'member_action_log',
        'member_post_log', 'tool_consent_log', 'threshold_log', 'like_events', 'like_flags', 'like_watch', 'guest_likes', 'guest_ip_likes', 'site_health_marks', 'csp_reports',
        'admin_edit_log', 'qr_issued', 'short_scans', 'member_active_days', 'biz_schema'];
    public const FOLLOWS_LIKE = ['/_cache$/', '/_counts?$/', '/_tally$/', '/_loads$/', '/^search_/', '/^crawl_/', '/^ip_/', '/_log$/', '/^tvchk_/', '/^_moved_/'];

    /** Staging's own: kept; never carried. Views and taps from staging visits are staging's (Jason, 2026-11-15, choice 5). */
    public const OWN = ['open_load_batches' /* a load's batch keys (IR-0001, 2026-11-24): what each batch did, so a resend does nothing twice */, 'control_alarms' /* the Control Board's alarms (decisions/0171 §7): each site raises its own */, 'control_incidents' /* their Incident Reports, IR-#### (the one engine, P12): each site's own, as its alarms are */, 'control_errors' /* the board's sensor: server errors on critical paths, path and status only, 7 days (0171 §5's note) */, 'control_maintenance' /* and its maintenance windows (0171 §2.5): each site's own */, 'system_pauses' /* what the system paused at a fault, with its alarm (decisions/0175 §3): each site's own, as its alarms are */, 'control_readings' /* the historian's readings (0171 §6): each site's own */, 'control_daily' /* and its daily rows */, 'control_log' /* the board's logbook (0171 §10): each site's own */, 'control_gauge_tasks' /* a gauge panel's Fix it requests and Jason's scope and target approvals (GaugeTasks): each site's own, as its logbook is */, 'control_gauge_task_steps' /* a Work queue task's steps (GaugeTasks::steps): each site's own, as its tasks are */, 'control_gauge_task_adrs' /* the decisions attached to a Work queue task (GaugeTasks::adrs): each site's own, as its tasks are */, 'preflight_results' /* each site's own pre-flight diagnosis (Preflight.php) */, 'preflight_runs' /* and its run log, each with its report */, 'staging_mail', 'staging_s3', 'staging_held', 'staging_allow', 'staging_pace', 'stg_plan', 'stg_base', 'stg_changes', 'stg_renumber', 'stg_id_refs', 'stg_conflicts', 'stg_outbox_seen', 'pub_journal', 'stg_bar_used',
        'journey_views', 'member_post_views', 'content_likes', 'journey_likes', 'gallery_downloads', 'gallery_uses', 'gallery_picks', 'gallery_pick_sends', 'gallery_guest', 'gallery_guest_items',
        'gallery_guest_keys', 'market_saves', 'market_contacts', 'search_opens', 'member_post_holds', 'member_post_prefs', 'messages', 'message_threads', 'message_members', 'business_messages',
        'comms_messages', 'comms_route' /* the one dispatcher's messages and their routes (decisions/0172): staging's own, as its messages are */, 'comms_setup_log' /* Communications' changes made on staging (0167) */, 'listing_requests', 'listing_request_sends' /* visitors' requests to unclaimed listings' emails and their pace (0172 §3.3): staging never sends them */, 'business_message_reads', 'business_message_mutes', 'guest_inquiries', 'guest_inquiry_messages', 'contact_requests', 'site_form_entries', 'verify_requests', 'verification_attempts',
        'age_confirmations', 'terms_acceptances', 'tool_consents', 'marketing_optins', 'account_closures', 'opt_out_requests', 'entity_referrals', 'connections', 'contact_shares',
        'business_contact_shares', 'community_group_invites', 'community_group_joins', 'community_invite_codes', 'ai_asks', 'test_records', 'user_blocks'];

    /** Never copied: empty on staging (StagingSite::EMPTY_TABLES and live's own records of staging and releases). */
    public const NEVER = ['tv_vectors' /* Supabase's, not MySQL: the vector index (Vectors.php), never copied */, 'comms_mailboxes', 'comms_mail', 'push_subs' /* connected mailboxes' sealed passwords and private mail, and push devices (decisions/0172 §4.6, §4.7): staging never connects or pushes */, 'vault_meta', 'vault_creds', 'vault_items', 'vault_ext', 'vault_log', 'email_confirmations', 'user_otp_challenges', 'user_identities', 'two_step_devices',
        'staff_recovery_codes', 'staff_mail_requests' /* Can't get the email? requests: sign-in recovery (Release 3f B8) */, 'guest_devices', 'support_grants', 'page_cache', 'staging_state', 'site_releases', 'site_release_settings', 'stg_outbox', 'identities'];

    /**
     * Kinds: the word a "kind:id" string, a Pulse key or a link uses, => its table. Every id of these tables found in
     * text (kind:id, links) is rewritten when a row is renumbered.
     */
    public const KINDS = ['entity' => 'entities', 'listing' => 'entities', 'business' => 'entities', 'landmark' => 'landmarks', 'guide' => 'resource_guides', 'post' => 'member_posts',
        'place' => 'places', 'journey' => 'journeys', 'order' => 'orders', 'experience' => 'experiences', 'user' => 'users', 'storefront' => 'storefronts', 'page' => 'site_pages',
        'workflow' => 'wf_workflows', 'topic' => 'topics', 'group' => 'community_groups', 'comment' => 'content_comments', 'story' => 'stories', 'intake' => 'crawler_raw_index'];

    /** Links in text that carry a record number: table => patterns (the number is the first group). */
    public const LINKS = [
        'entities' => ['#/listing/view\.php\?id=(\d+)#', '#/listing/(\d+)(?![\d/])#', '#/biz/(\d+)(?!\d)#'],
        'resource_guides' => ['#/guide\.php\?id=(\d+)#', '#/guides?/(\d+)(?!\d)#'],
        'journeys' => ['#/journey\.php\?id=(\d+)#', '#/journeys?/(\d+)(?!\d)#'],
        'places' => ['#/place\.php\?id=(\d+)#'],
        'landmarks' => ['#/landmark\.php\?id=(\d+)#'],
        'member_posts' => ['#/post\.php\?id=(\d+)#'],
        'experiences' => ['#/experience\.php\?id=(\d+)#'],
    ];

    /**
     * Columns that hold a record number, named here where the name doesn't say which table (the rest are read from
     * their names: <thing>_id → the table of <thing>, by NAMES). table.column => table it points to.
     */
    public const REFS = [
        'order_refund_requests.ledger_id' => 'order_ledger', 'order_refund_requests.asked_by' => 'users', 'order_payment_cancels.ledger_id' => 'order_ledger',   // M2
        '*.owner_user_id' => 'users', '*.created_by' => 'users', '*.updated_by' => 'users', '*.approved_by' => 'users', '*.decided_by' => 'users', '*.by_user' => 'users',
        '*.member_user_id' => 'users', '*.author_id' => 'users', '*.parent_id' => '=', '*.replaces_id' => '=', '*.ref_id' => '?kind', '*.from_id' => '?kind', '*.to_id' => '?kind',
        'listing_access.entity_id' => 'entities', 'identity_pairs.a_id' => 'entities', 'identity_pairs.b_id' => 'entities', 'identity_merges.keep_id' => 'entities', 'identity_merges.drop_id' => 'entities',
        'hub_stages.hub_id' => 'hubs', 'authority_sources.terms_by' => 'users', 'authority_sources.on_by' => 'users', 'hub_stages.set_by' => 'users', 'engine_steps.assigned_to' => 'users', 'engine_steps.done_by' => 'users', 'identity_merges.undone_by' => 'users',
        // the columns the register couldn't place (decisions/0165's open item, Release B): found from the code's own queries and
        // JOINs, each checked by hand; '' is not one of our record numbers (an outside id, a batch number)
        'community_groups.owner_id' => 'users', 'people_groups.owner_id' => 'users', 'address_contacts.owner_id' => 'users', 'contact_shares.owner_id' => 'users', 'contact_shares.viewer_id' => 'users',
        'contact_requests.owner_id' => 'users', 'contact_requests.requester_id' => 'users', 'people_connections.requester_id' => 'users', 'people_connections.addressee_id' => 'users', 'prospects.owner_id' => 'users',
        'wf_items.owner_id' => 'users', 'crm_customers.owner_id' => 'entities', 'crm_tags.owner_id' => 'entities', 'crm_sync.owner_id' => 'entities', 'commerce_settings.owner_id' => 'entities',
        'tax_rates.owner_id' => 'entities', 'ship_rules.owner_id' => 'entities', 'price_lists.owner_id' => 'entities', 'site_pages.owner_id' => 'entities', 'site_form_entries.owner_id' => 'entities',
        'biz_quotes.owner_id' => 'entities', 'biz_quote_lines.owner_id' => 'entities', 'biz_quote_log.owner_id' => 'entities', 'biz_payments.owner_id' => 'entities', 'biz_codes.owner_id' => 'entities',
        'biz_code_uses.owner_id' => 'entities', 'biz_files.owner_id' => 'entities', 'subscriptions.owner_id' => '?kind', 'cart_plans.owner_id' => '?kind', 'account_domains.owner_id' => '?kind', 'domain_cart.owner_id' => '?kind', 'wallet_cards.owner_id' => '?kind', 'domain_events.domain_id' => 'account_domains', 'domain_cart.domain_id' => 'account_domains', 'wallet_assignments.card_id' => 'wallet_cards', 'promotion_uses.owner_id' => '?kind',
        'market_offers.buyer_id' => 'users', 'market_offers.seller_id' => 'users', 'market_orders.buyer_id' => 'users', 'market_contacts.buyer_id' => 'users', 'market_notes.about_id' => 'users',
        'subscriptions.payer_id' => 'users', 'people_invites.inviter_id' => 'users', 'people_invites.accepted_user_id' => 'users', 'user_blocks.blocked_id' => 'users', 'messages.sender_id' => 'users',
        'content_reports.reporter_id' => 'users', 'listing_access.granted_by_user_id' => 'users', 'entity_referrals.referrer_user_id' => 'users', 'entity_referrals.requested_by_user_id' => 'users', 'crawler_raw_index.reviewed_by_user_id' => 'users',
        'crawler_sources.added_by_user_id' => 'users', 'crawl_jobs.queued_by_user_id' => 'users', 'verification_attempts.reviewed_by_user_id' => 'users', 'verify_requests.admin_user_id' => 'users', 'disputes.filed_by_user_id' => 'users',
        'guest_inquiries.moved_user_id' => 'users', 'order_ledger.actor_user_id' => 'users', 'order_events.actor_user_id' => 'users', 'order_inbox.actor_user_id' => 'users', 'sales_territories.rep_id' => 'users',
        'sales_territories.manager_id' => 'users', 'community_charters.successor_id' => 'users', 'content_tags.by_id' => 'users', 'cleanup_settings.by_id' => 'users', 'cleanup_ledger.by_id' => 'users',
        'threshold_log.by_id' => 'users', 'role_log.actor_id' => 'users', 'staff_log.actor_staff_id' => 'staff_accounts', 'crawler_raw_index.matched_entity_id' => 'entities', 'suggestions.same_as_id' => 'entities',
        'promotions.lead_id' => 'entities', 'live_events.venue_entity_id' => 'entities', 'connections.target_id' => 'entities', 'identity_jobs.record_id' => 'entities', 'identity_applied.record_id' => 'entities',
        'identity_facts.record_id' => '?kind', 'role_grants.scope_id' => '?kind', 'content_reports.target_id' => '?kind', 'search_index.item_id' => '?kind', 'user_links.target_id' => '?kind',
        'tag_counts.context_id' => '?kind', 'gallery_album_items.item_id' => 'gallery_items', 'gallery_guest_items.item_id' => 'gallery_items', 'gallery_picks.item_id' => 'gallery_items', 'gallery_downloads.item_id' => 'gallery_items',
        'gallery_files.item_id' => 'gallery_items', 'gallery_versions.item_id' => 'gallery_items', 'gallery_uses.item_id' => 'gallery_items', 'market_photos.item_id' => 'gallery_items', 'market_offers.item_id' => 'market_items',
        'market_item_versions.item_id' => 'market_items', 'market_cart.item_id' => 'market_items', 'market_tickets.item_id' => 'market_items', 'market_downloads.item_id' => 'market_items', 'market_notes.offer_id' => 'market_offers',
        'market_offer_events.offer_id' => 'market_offers', 'order_line_parts.line_id' => 'order_lines', 'order_line_costs.line_id' => 'order_lines', 'order_proofs.line_id' => 'order_lines', 'order_files.line_id' => 'order_lines',
        'order_lots.line_id' => 'order_lines', 'order_events.line_id' => 'order_lines', 'vendor_jobs.line_id' => 'order_lines', 'collateral.source_line_id' => 'order_lines', 'collateral.first_order_id' => 'orders',
        'order_lines.project_id' => 'order_projects', 'order_lots.dest_id' => 'order_addresses', 'order_packs.dest_id' => 'order_addresses', 'order_packs.label_id' => 'order_files', 'order_pack_items.lot_id' => 'order_lots',
        'order_pack_items.pack_id' => 'order_packs', 'order_work_order_log.wo_id' => 'order_work_orders', 'order_lines.vendor_id' => 'vendors', 'order_work_orders.vendor_id' => 'vendors', 'collateral.vendor_id' => 'vendors',
        'product_vendor_parts.vendor_id' => 'vendors', 'orders.promo_id' => 'promotions', 'subscriptions.promo_id' => 'promotions', 'promotion_uses.promo_id' => 'promotions', 'promotion_uses.sub_id' => 'subscriptions',
        'subscription_addons.sub_id' => 'subscriptions', 'subscription_charges.sub_id' => 'subscriptions', 'biz_quote_lines.quote_id' => 'biz_quotes', 'biz_quote_log.quote_id' => 'biz_quotes', 'biz_payments.quote_id' => 'biz_quotes',
        'biz_code_uses.quote_id' => 'biz_quotes', 'biz_files.quote_id' => 'biz_quotes', 'biz_quotes.code_id' => 'biz_codes', 'biz_code_uses.code_id' => 'biz_codes', 'disputes.claim_id' => 'listing_claims',
        'verify_requests.claim_id' => 'listing_claims', 'entities.nexus_id' => 'nexus', 'content_template_versions.template_id' => 'content_templates', 'content_template_tries.template_id' => 'content_templates', 'identity_fact_sources.fact_id' => 'identity_facts',
        'identity_applied.fact_id' => 'identity_facts', 'topic_facts.fact_id' => 'heritage_facts', 'topic_relations.a_id' => 'topics', 'topic_relations.b_id' => 'topics', 'community_groups.welcome_post_id' => 'community_posts',
        'guest_inquiry_messages.inquiry_id' => 'guest_inquiries', 'guide_finds.suggestion_id' => 'suggestions', 'content_tags.find_id' => 'guide_finds', 'search_drafts.edit_id' => 'search_edits', 'price_list_rules.list_id' => 'price_lists',
        'short_scans.code_id' => 'short_codes', 'stock_uses.stock_id' => 'stock_images', 'video_jobs.media_id' => 'member_post_media', 'member_action_log.action_id' => 'member_actions', 'cluster_overrides.forced_cluster_id' => 'micro_clusters',
        'cleanup_ledger.run_id' => 'cleanup_runs', 'crawl_run_items.run_id' => 'crawl_runs', 'crawl_service_calls.run_id' => 'crawl_runs', 'crawl_costs.run_id' => 'crawl_runs', 'crawl_requests.run_id' => 'crawl_runs',
        'situation_gaps.tier1_job_id' => 'crawl_jobs', 'sales_cards.job_id' => 'ai_jobs', 'town_crawl_jobs.anchor_id' => 'anchors', 'wf_items.item_id' => '', 'wf_history.item_id' => '',
        'identity_merges.run_id' => '', 'landmarks.gnis_id' => '', 'rec_places.ridb_id' => '', 'place_profile.geo_id' => '', 'site_health_marks.finding_id' => '',
        'email_sends.message_id' => '', 'market_pay_events.event_id' => '', 'market_gateways.account_id' => '', 'market_gateways.location_id' => '', 'market_square_map.variation_id' => '',
        'market_square_map.item_id' => '', 'subscriptions.card_id' => '', 'subscription_charges.charge_id' => '', 'subscription_charges.receipt_id' => '', 'orders.charge_id' => '',
        'orders.invoice_id' => '', 'orders.qb_customer_id' => '', 'order_inbox.qb_id' => '', 'order_inbox.qb_customer_id' => '', 'crm_customers.qb_customer_id' => '',
        'client_listings.qb_customer_id' => '', 'products.qb_item_id' => '', 'qb_connection.realm_id' => '', 'qb_connection.item_id' => '', 'entity_metadata.entity_id' => 'entities', 'storefronts.entity_id' => 'entities',
        // the one dispatcher (decisions/0172 step 2): who a message is to, from, and its route
        'comms_messages.to_user' => 'users', 'comms_messages.to_entity' => 'entities', 'comms_messages.sender_id' => 'users', 'comms_route.message_id' => 'comms_messages',
        'pulse_flags.user_id' => 'users', 'comms_mail_blocks.user_id' => 'users', 'pulse_forwards.user_id' => 'users', 'pulse_views.user_id' => 'users', 'pulse_forwards.to_user' => 'users', 'pulse_tags.owner_id' => '', 'pulse_tags.made_by' => 'users', 'comms_overseers.user_id' => 'users', 'comms_overseers.added_by' => 'users', 'comms_routing.changed_by' => 'users',
        'comms_controls.by_user' => 'users', 'comms_setup_log.user_id' => 'users', 'comms_mailboxes.owner_id' => '', 'comms_mailboxes.approved_by' => 'users', 'comms_mailboxes.made_by' => 'users',
        'comms_mail.mailbox_id' => 'comms_mailboxes', 'comms_mail.comms_id' => 'comms_messages', 'push_subs.user_id' => 'users', 'content_comments.parent_id' => 'content_comments', 'content_comments.hidden_by' => 'users',
        'guest_inquiries.user_id' => 'users', 'listing_request_sends.inquiry_id' => 'guest_inquiries', 'listing_requests.entity_id' => 'entities', 'listing_request_sends.entity_id' => 'entities', 'comms_route.provider_id' => '',
    ];

    /** <thing>_id → table, where the plural isn't just <thing>s. */
    public const NAMES = ['entity' => 'entities', 'listing' => 'entities', 'business' => 'entities', 'biz' => 'entities', 'user' => 'users', 'member' => 'users', 'staff' => 'staff_accounts',
        'category' => 'categories', 'guide' => 'resource_guides', 'post' => 'member_posts', 'story' => 'stories', 'group' => 'community_groups', 'workflow' => 'wf_workflows',
        'wf' => 'wf_workflows', 'page' => 'site_pages', 'album' => 'gallery_albums', 'customer' => 'crm_customers', 'hub' => 'hubs', 'cluster' => 'micro_clusters', 'nation' => 'nations',
        'topic' => 'topics', 'situation' => 'situations', 'product' => 'products', 'item' => '?', 'thread' => 'message_threads'];

    /** JSON and settings blobs that hold record numbers: table.column => [JSON path (a.b.*.c) => table]. */
    public const JSON = [
        'site_pages.layout' => ['blocks.*.listing_id' => 'entities', 'blocks.*.guide_id' => 'resource_guides', 'blocks.*.ids.*' => '?kind'],
        'site_block_versions.data' => ['*.listing_id' => 'entities', '*.guide_id' => 'resource_guides'],
        'content_template_versions.body' => ['*.listing_id' => 'entities'],
        'user_settings.value' => ['saved.*.listing_id' => 'entities'],
        'pulse_marks.data' => ['entity_id' => 'entities', 'user_id' => 'users'],
    ];

    /** Columns whose text is searched for kind:id strings and links (notes, logs, descriptions, messages, Pulse keys). */
    public const TEXT_COLS = ['/(^|_)(note|notes|body|text|description|summary|msg|message|detail|details|key|ref|url|link|html|content|about)$/'];

    /** Store keys that carry an id: pattern (group 1 = table kind, group 2 = id). */
    public const STORE_KEYS = ['#^(entity|entities|listing|guide|journey|post|place|user)(s?)/(\d+)/#'];

    /** The class of a table ('follows' when it has none: see noClass()). */
    public static function classOf(string $t): string
    {
        if (in_array($t, self::NEVER, true)) return 'never';
        if (in_array($t, self::OWN, true)) return 'own';
        if (in_array($t, self::MONEY, true)) return 'money';
        if (in_array($t, self::RECORDS, true)) return 'records';
        return 'follows';
    }

    /** Is the table named here (by name or pattern)? A table that isn't is shown as "no class" (treated as follows live). */
    public static function named(string $t): bool
    {
        foreach ([self::NEVER, self::OWN, self::MONEY, self::RECORDS, self::FOLLOWS] as $l) if (in_array($t, $l, true)) return true;
        foreach (self::FOLLOWS_LIKE as $p) if (preg_match($p, $t)) return true;
        return false;
    }

    public static function carried(string $t): bool { return self::classOf($t) === 'records'; }

    /** The table a column points to: a table name, '=' (its own table), '?kind' (a kind column beside it says), '' when it holds no record number, null when it looks like one and isn't known. */
    public static function refOf(string $t, string $col, string $type = 'int'): ?string
    {
        $c = strtolower($col);
        if (isset(self::REFS[$t . '.' . $c])) return self::REFS[$t . '.' . $c];
        if (isset(self::REFS['*.' . $c])) return self::REFS['*.' . $c] === '=' ? $t : self::REFS['*.' . $c];
        if ($c === 'id') return '';
        if (!preg_match('/^(.+)_id$/', $c, $m)) return '';
        if (!preg_match('/int/i', $type)) return '';   // text ids (a Stripe id, a Google place id) aren't our record numbers
        $thing = (string) preg_replace('/^(old|new|from|to|parent|main|primary|linked|home|via|source|target|their|my|other)_/', '', $m[1]);
        if (isset(self::NAMES[$thing])) return self::NAMES[$thing] === '?' ? null : self::NAMES[$thing];
        foreach ([$thing . 's', $thing . 'es', preg_replace('/y$/', 'ies', $thing), $thing] as $guess) if (in_array($guess, self::RECORDS, true) || in_array($guess, self::MONEY, true)) return $guess;
        return null;
    }
}
