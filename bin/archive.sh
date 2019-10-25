dir=`dirname $0`
dir="$dir/../data/"
newdir="$dir/archived/"
echo "PATH:$dir"

for f in `find $dir -maxdepth 1 -type f -mtime +180 \( -name "*.pdf" -o -name "*.xlsx" -o -name "*.xls" \)`; 
do 
	ls -la $f  
	basenm=`basename "$f"`
	echo "mv $f $newdir"
	mv -vf $f $newdir
done;
#remove files
for f in `find $dir -maxdepth 1 -type f -mtime +270 \( -name "*.pdf" -o -name "*.xlsx" -o -name "*.xls" \)`;
do
        ls -la $f
        basenm=`basename "$f"`
        echo "rm $f"
        rm -vf $f 
done;
