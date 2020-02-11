#!/usr/bin/env bash

# Base test directory (where this script is located).
GLOB="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"



# Dump the tail of Apache's error log, if there are error screenshots.
if [ `ls -1 ${GLOB}/test_results/error_screenshots/ | wc -l` -gt 0 ];
then
  # There are error screenshots. Also check for errors in the logs.
  tail -n 50 /var/log/apache2/error.log;
fi # We could put an else and nicely die with exit 0, but the code below doesn't error anymore when there are no files.



# Loop through screenshot files (oldest to newest).
for file in `ls -1 -t -r ${GLOB}/test_results/error_screenshots/ | grep -F .png`; do
    echo "Uploading file: ${file}";

    # Upload to transfer.sh, this command will output the URL on which the
    # uploaded file can be reached.
    RETURN=`curl -s -H "Max-Days: 2" --upload-file "${GLOB}/test_results/error_screenshots/${file}" https://transfer.sh`;
    echo $RETURN;
    if [[ $RETURN == "Could not save metadata" ]];
    then
        # Transfer.sh service often fails.
        echo "Transfer.sh failed, emailing file...";
        mutt -s "Travis failure" -a "${GLOB}/test_results/error_screenshots/${file}" -- I.F.A.C.Fokkema@LUMC.nl < <(echo "Travis run failed. Screenshot attached.")
    fi

    rm -f "${GLOB}/test_results/error_screenshots/${file}"
done
