echo "Install and setup apache"
sudo apt-get -qq update > /dev/null
sudo apt-get -qq install -y --force-yes apache2 libapache2-mod-php5 php5-curl php5-intl php5-gd php5-idn php-pear php5-imagick php5-imap php5-mcrypt php5-memcache php5-ming php5-ps php5-pspell php5-recode php5-snmp php5-sqlite php5-tidy php5-xmlrpc php5-xsl php5-mysql
## exim4
## smtp
sudo a2enmod rewrite

for i in "$@"
do
    case $i in
        -ga=*|--githubaccount=*)
            LOCALHOSTFOLDER="${i#*=}"
			#The input is refering to the github account.
			#For local development: /home/travis/build/dasscheman
			#For LUMC development : /home/travis/build/LUMC
        ;;
        *)
            echo Unknown input
            echo Usage:
            column -t -s "/" <<<'    -l=<folder> /|/ --localhost=<folder> / Give the first localhost folder when it is not "svn". This is used in the Travis CI test.
        -c /|/ --continueall / If set, it will not ask for actions during convert, but always continues with convert. This might create corrupt phpunit test files.'
            echo "Specify no file when you want te test all testfiles in the phpunit_selenium folder."
            exit
        ;;
    esac
done

## Setting the home directory for localhost.
# convert_selenium_to_phpunit.sh assumes that the files are in a folder with this pattern: */svn/*/trunk
# * can be a folder or nothing.
# This is done because of the local folder structure, which I don't want to change.
echo 'Set localhost directory.'
sudo sed -i -e "s,/var/www,/home/travis/build/${LOCALHOSTFOLDER},g" /etc/apache2/sites-available/default
sudo sed -i -e "s,AllowOverride[ ]None,AllowOverride All,g" /etc/apache2/sites-available/default

echo 'Intall mail agent.'
# Pass the -y flag to suppress interactive requests.
sudo apt-get -qq -y install exim4 apcupsd nmap

echo 'Restart apache2'
sudo /etc/init.d/apache2 restart
