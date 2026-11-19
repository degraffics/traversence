#!/bin/bash
S=/tmp/claude-0/-home-user/a0bc3900-b870-5aed-8e78-1fb100348626/scratchpad; M=/home/user/traverse-3way-sync; R=$S/rck
rm -rf $R && mkdir -p $R/app/lib $R/app/ui $R/website/js $R/website/listing/api
cp $M/sync-app/lib/Claims.php $R/app/lib/; cp $M/sync-app/ui/faq.md $R/app/ui/
cp $M/sync-storage/claim.php $R/website/; cp $M/sync-storage/js/claim.js $R/website/js/
cp $M/sync-storage/listing/view.php $R/website/listing/; cp $M/sync-storage/listing/api/claim.php $R/website/listing/api/
set -e
for b in ${CK_PARTS:-ck}; do python3 $S/ck/build-$b.py $R >/dev/null; done
for f in $(find $R -name '*.php'); do php -l $f >/dev/null; done
for f in $(find $R -name '*.js'); do node --check $f; done
echo OK
