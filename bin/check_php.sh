for f in `find . -path vendor.bak -prune -o -name "*.php" `;
do
	echo $f;
	php -l $f
done;
