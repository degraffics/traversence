#!/bin/bash
# the place page: rebuild from the live mirror
S=/tmp/claude-0/-home-user/a0bc3900-b870-5aed-8e78-1fb100348626/scratchpad; M=/home/user/traverse-3way-sync; R=$S/rpp
rm -rf $R && mkdir -p $R/website/{api,js,discovery,place,listing,hub} $R/app/ui
cp $M/sync-app/ui/{engage-ui,explore-hero}.php $M/sync-app/ui/faq.md $R/app/ui/
cp $M/sync-storage/api/engage.php $R/website/api/; cp $M/sync-storage/js/engage.js $R/website/js/
cp $M/sync-storage/discovery/index.php $R/website/discovery/; cp $M/sync-storage/place/{landmark,recreation}.php $R/website/place/
cp $M/sync-storage/listing/view.php $R/website/listing/; cp $M/sync-storage/hub/index.php $R/website/hub/
set -e
for b in ${PP_PARTS:-pp}; do python3 $S/pp/build-$b.py $R >/dev/null; done
for f in $(find $R -name '*.php'); do php -l $f >/dev/null; done
for f in $(find $R -name '*.js'); do node --check $f; done
echo OK
