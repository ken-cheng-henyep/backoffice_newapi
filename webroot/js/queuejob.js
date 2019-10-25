;(function($){


    var showWindow = function (id, template, title) {

        var dfd = new jQuery.Deferred();
        var result = false;

        var $elm = $("<div>");
        $elm.prop('id', id)
        .appendTo("body")
        .kendoWindow({
            actions: [],
            width: "300px",
            resizable: false,
            modal: true,
            title: title,
            visible: false,
            close: function (e) {
                this.destroy();
                dfd.resolve(result);
            }
        });
        var api = $elm.data('kendoWindow');
        api.content($(template).html()).center().open()

        return {$elm: $elm, api: api, close: function(){ $elm.data('kendoWindow').close();} , open: function(){ $elm.data('kendoWindow').open();} , promise: dfd.promise()};
    };


    var startQueueJob = function(queueUrl, jobData, totalRecord){

        var $form = $('.users.form form');
        var insId = (new Date).getTime()+'_'+ ((Math.random() *10000) << 0);
        var frameName = 'ifr_'+insId;
        var callbackName = 'cb_'+insId;

        var windowInstance = null;


        var $rstContent= $('<div class="win-result"><b><div class="caption"></div></b><p></p><button type="button"  data-role="button" class=" btn-close k-button">Close</button></div>');


        $rstContent.on('click','.btn-close', function(evt){
            windowInstance.close();
        })

        $form.find('button').prop('disabled', true);

        var timeToRefresh = 2;
        if(totalRecord > 2000){
            timeToRefresh = totalRecord / 1000;
        }
        if(timeToRefresh > 10) timeToRefresh = 10;

        var nextCheckTimer;
        var nextCheckLoader; 

        var jobId = null;
        var isLastTry = false;
        var lastStatus = 'unknown';
        var lastProgress = 0;

        var cancel = function(){

            clearTimeout(nextCheckTimer);
            if(!jobId){
                alert('Does not have jobId')
                return;
            }

            if(nextCheckLoader) nextCheckLoader.abort();
            nextCheckLoader = $.getJSON(startQueueJob.cancelUrl+'/'+jobId , onDataLoaded).error(onDataLoadError)

            if(windowInstance){
                windowInstance.close();
            }
        }

        // Check the server status 
        var nextCheck = function(){
            clearTimeout(nextCheckTimer);
            if(!jobId){
                alert('Does not have jobId')
                return;
            }
            if(nextCheckLoader) nextCheckLoader.abort();
            nextCheckLoader = $.getJSON(startQueueJob.checkUrl+'/'+jobId, onDataLoaded).error(onDataLoadError)
        }

        // If server said any error for the job.
        var onDataLoadError = function(e){
            clearTimeout(nextCheckTimer);
            nextCheckLoader = null;
            $form.find('button').prop('disabled', false);
            windowInstance.$elm.find('.win-body').empty()
            windowInstance.api.setOptions({actions:['Close']})
            
            $rstContent.find('.caption').text('Error');
            $rstContent.find('p').text('Connection error.');
            $rstContent.appendTo (windowInstance.$elm.find('.win-body'));
            if(e.status == '403'){
                $rstContent.find('p').text('Your session has expired.');
            }else if(e.status == '500' && !isLastTry){
                isLastTry = true;
                // Try one more time
                nextCheckTimer = setTimeout(nextCheck, 10 * 1000)
            }
        }
        // If server said any resposne for the job.
        var onDataLoaded = function(rst){
            isLastTry = false;
            clearTimeout(nextCheckTimer);
            nextCheckLoader = null;

            lastStatus = rst.status;

            if(rst.status == 'added'){

                jobId = rst.id;

                if(windowInstance) {
                    pb.value( 0 )
                    windowInstance.$elm.find('.status').text('Preparing...');
                }
                $form.find('button').prop('disabled', false)

                nextCheckTimer = setTimeout(nextCheck, timeToRefresh * 1000)

            }else if(rst.status == 'pending'){

                if(windowInstance) {
                    pb.value( 0 )
                    // windowInstance.$elm.find('.status').text('Waiting for process...');
                }

                nextCheckTimer = setTimeout(nextCheck, timeToRefresh * 1000)

            }else if(rst.status == 'progress' || rst.status == 'processing'){

                lastProgress = rst.progress;

                if(windowInstance) {
                    pb.value( ((rst.progress*100)<<0) )
                    windowInstance.$elm.find('.status').text('Finished '+ ((rst.progress*100)<<0) + '% ...');
                }

                nextCheckTimer = setTimeout(nextCheck, timeToRefresh * 1000)

            }else if(rst.status == 'done'){
                lastProgress = 1;                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              

                if(rst.url){
                    location.href = rst.url;
                }

                if(windowInstance) {
                    if(rst.message){
                        windowInstance.$elm.find('.win-body').text('');
                        windowInstance.api.setOptions({actions:['Close']})

                        $rstContent.find('p').text(rst.message);

                        $rstContent.appendTo (windowInstance.$elm.find('.win-body'));
                        $rstContent.find('[data-role=button]').kendoMobileButton()

                    }else{
                        windowInstance.close();

                    }
                }

            }else if(rst.status == 'error' || rst.status == 'fail'){

                if(windowInstance) {
                    windowInstance.$elm.find('.win-body').empty();
                    windowInstance.api.setOptions({actions:['Close']})

                    $rstContent.find('.caption').text('Error');
                    $rstContent.find('p').text(rst.message);
                    $rstContent.appendTo (windowInstance.$elm.find('.win-body'));
                    $rstContent.find('[data-role=button]').kendoMobileButton()
                }

            }
        }

        $.post(queueUrl, jobData, onDataLoaded, 'json').error(onDataLoadError)

        windowInstance = showWindow('win_'+insId, '<div><div class="win-body"><div class="status"></div><div class="pb" style="width: 100%;margin:10px 0;"></div><button type="button" class="k-button btn-refresh hidden">Update Status</button> <button type="button" class="k-button btn-cancel">Cancel</button></div></div>','Excel Download')

        windowInstance.$elm.on('click', '.btn-refresh', function(){
            nextCheck();
            $(this).prop('disabled', true);
        })

        windowInstance.$elm.on('click', '.btn-cancel', function(){
            clearTimeout(nextCheckTimer);
            cancel();
        })

        windowInstance.$elm.on('click', '.btn-close', function(){
            clearTimeout(nextCheckTimer);
            if(windowInstance)
            windowInstance.close();
        })

        var pb = windowInstance.$elm.find(".pb").kendoProgressBar({
            min: 0,
            max: 100,
            type: "value",
            showStatus: false,
            animation: {
                duration: 400
            }
        }).data("kendoProgressBar");

        return {
            getJobId: function(){return jobId},
            cancel: function(){ return cancel();},
            show: function(){ windowInstance.open();},
            hide: function(){ windowIsntance.close();}
        } 
    }
    startQueueJob.cancelUrl = '';
    startQueueJob.checkUrl = '';

    window.showWindow = showWindow;
    window.startQueueJob = startQueueJob;


})(jQuery);