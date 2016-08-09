#!/usr/bin/env bash

# Base test directory (where this script is located).
GLOB="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Loop through screenshot files (oldest to newest).
for file in `ls -1 -t -r ${GLOB}/test_results/error_screenshots/*.png`; do
    echo "Uploading file: ${file}";

    # Upload to transfer.sh, this command will output the URL on which the
    # uploaded file can be reached.
    curl --upload-file ${file} http://transfer.sh
done
