<?php
return [
    'Queue' => [
        //'workermaxruntime' => 630,//60,
	'workermaxruntime' => 28800, // sec = 8hr
        'sleeptime' => 60,
        'defaultworkerretries' => 0, //no retry,
	    //'cleanuptimeout' => 2592000, // 30 days
        'cleanuptimeout' => 31622400, //366 days
	//dev
        //'sleeptime' => 20,
        //'workermaxruntime' => 300,//60,
        //Default timeout after which a job is requeued if the worker doesn't report back:
        //'defaultworkertimeout' => 10,
	//dev end
    ],
];
