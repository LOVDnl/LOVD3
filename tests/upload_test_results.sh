#!/usr/bin/env bash

# Base test directory (where this script is located).
GLOB="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"



# Dump the tail of Apache's error log, if there are error screenshots.
if [ `ls -1 ${GLOB}/test_results/error_screenshots/ | wc -l` -gt 0 ];
then
  # There are error screenshots. Also check for errors in the logs.
  tail -n 50 /var/log/apache2/error.log | grep -v ':notice]';
fi # We could put an else and nicely die with exit 0, but the code below doesn't error anymore when there are no files.



# Loop through screenshot files (oldest to newest).
for file in `ls -1 -t -r ${GLOB}/test_results/error_screenshots/ | grep -F .png`; do
    echo "Uploading file: ${file}";

    # Transfer.sh is dead, again, so let's stop relying on it.
    # It is open source, so we could try to set it up on one of our own servers, though?
    # For now, upload to file.io.
    # This command will output the URL on which the uploaded file can be reached.
    RETURN=`curl -s -F "file=@${GLOB}/test_results/error_screenshots/${file}" https://file.io?expires=1w`;
    if [[ $(echo $RETURN | cut -b 1) != '{' || $(echo $RETURN | jq .success) != 'true' ]];
    then
        # file.io service failed.
        echo "Upload failed, emailing file...";
        mutt -s "Travis failure" -a "${GLOB}/test_results/error_screenshots/${file}" -- I.F.A.C.Fokkema@LUMC.nl < <(echo "Travis run failed. Screenshot attached.")
    else
        echo $RETURN | jq -r .link;
        echo -n 'Expires in ';
        echo -n $RETURN | jq -r .expiry;
    fi

    rm -f "${GLOB}/test_results/error_screenshots/${file}"
done
