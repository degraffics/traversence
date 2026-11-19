#!/bin/bash
# Get Local R2: rebuild from the live mirror, every part in order
S=/tmp/claude-0/-home-user/a0bc3900-b870-5aed-8e78-1fb100348626/scratchpad; M=/home/user/traverse-3way-sync; R=$S/rr2
rm -rf $R && mkdir -p $R/website/directory $R/website/js $R/website/listing $R/app/lib $R/app/business $R/app/ui
cp $M/sync-storage/directory/index.php $R/website/directory/
cp $M/sync-storage/js/{tv-conf,map-view,engage,search-panel}.js $R/website/js/
mkdir -p $R/website/api; cp $M/sync-storage/api/search_listings.php $R/website/api/
cp $M/sync-storage/listing/view.php $R/website/listing/
cp $M/sync-app/lib/{ListingActions,UniversalSearch}.php $R/app/lib/
cp $M/sync-app/business/portal.php $R/app/business/
cp $M/sync-app/ui/{engage-ui,header,faq.md}* $R/app/ui/ 2>/dev/null; cp $M/sync-app/ui/faq.md $M/sync-app/ui/header.php $M/sync-app/ui/engage-ui.php $R/app/ui/
set -e
for b in $R2_PARTS; do python3 $S/build-$b.py $R >/dev/null; done
for f in $(find $R -name '*.php'); do php -l $f >/dev/null; done
for f in $(find $R -name '*.js'); do node --check $f; done
echo OK
