#!/bin/bash
# This script is intended to be used to run tests that validate the correct
# packaging / install behavior of XDMoD. For example checking the
# file permissions are correct on the RPM packaged files.

exitcode=0

# Check that there are no development artifacts installed in the RPM
if rpm -ql xdmod | fgrep .eslintrc.json; then
    echo "Error eslintrc files found in the RPM"
    exitcode=1
fi

for file in open_xdmod/build/*.tar.gz;
do
    echo "Checking $file"
    if tar tf "${file}" | fgrep .eslintrc.json; then
        echo "Error eslintrc files found in build tarball $file"
        exitcode=1
    fi
done

# Check that the unknown resource is in the database with key 0
unkrescount=$(echo 'SELECT COUNT(*) from resourcetype WHERE id = 0 and abbrev = '"'"'UNK'"'" | mysql -N modw)
if [[ "${unkrescount}" -ne 1 ]]
then
    echo "Missing / inconsistent 'UNK' row in modw.resourcetype"
    exitcode=1
fi

# Check that the various scripts have not left any files around in the tmp
# directory
FIND_CRITERIA=(-type f -newer /usr/share/xdmod/html/index.php '(' -user xdmod -o -user apache ')')
if find /tmp "${FIND_CRITERIA[@]}" | grep -q  .
then
    echo "Unexpected files found in temporary directory"
    find /tmp "${FIND_CRITERIA[@]}" -print0 | xargs -0 ls -la
    exitcode=1
fi

# ensure tables absent
tblcount=$(echo 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '"'"'moddb '"'"' AND table_name = '"'"'ReportTemplateACL'"'" | mysql -N information_schema)
if [[ "${tblcount}" -ne 0 ]]
then
    echo "Extraneous tables found in database"
fi

if ! mysqldump -d moddb report_template_acls | grep -q "ON DELETE CASCADE"
then
    echo "Missing/incorrect foreign key constraint on report_template_acls table"
    exitcode=1
fi

# Confirm that xdmod-check-config finds the RPM installation details
if ! xdmod-check-config | grep -q 'RPM Installed Packages'
then
    echo "Missing RPM information from xdmod-check-config"
    exitcode=1

fi

# Confirm that the user manual is built
# Check if table of contents tree properly links to corresponding file and each
# html file exists
input_directory=$XDMOD_INSTALL_DIR/html/user_manual
# Get all links in table of content tree
link_array=($(grep -o '<li class="toctree-l1"><a class="reference internal" href="[^"]*"' "$input_directory/index.html" | sed -E 's/.*href="([^"]*)"/\1/'))

# Check if corresponding HTML files exist for each link
for link in "${link_array[@]}"; do
    html_file="$input_directory/${link}"

    if [[ ! -f "$html_file" ]]; then
        echo "$html_file does not exist."
        exitcode=1
    fi
done

# Check if user manual is being properly hosted on the server
if [ !$(curl -I -s -o /dev/null -w "%{http_code}" -k https://localhost:443/user_manual/index.html) == "200" ];
then
    echo "User Manual was not built"
    exitcode=1
fi

if !(curl -s -k https://localhost:443/user_manual/index.html | grep -q h1)
then
    echo "User Manual is missing html"
    exitcode=1
fi

exit $exitcode
