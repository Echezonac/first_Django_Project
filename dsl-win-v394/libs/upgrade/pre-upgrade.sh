#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
/Applications/XAMPP/ds-plugins/ds-cli/platform/mac/boot.sh php -f "$DIR/pre-upgrade.php"
