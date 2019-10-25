dir=`dirname $0`
echo $dir
cd $dir
date
#chmod +w ../tmp/cache/
echo "check existing worker"
ps=`ps -ef |grep "queue runworker"|grep -v "grep" |wc -l`
#./cake queue kill -v 2>&1 >> /tmp/queue_worker.log
#./cake queue runworker -v 2>&1 >> /tmp/queue_worker.log
echo "$ps process"
if [ "$ps" -eq 0 ]
then
        echo "run worker now"
        ./cake queue runworker -v 2>&1 >> /tmp/queue_worker.log &
else
        echo "worker already running"
fi
