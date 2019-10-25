<div class="users form search-input">
<?= $this->Flash->render('auth') ?>
<div id="errdialog"></div>

<?= $this->Form->create(null, ['url' => ['controller' => 'SettlementBatch', 'action' => 'search'] ]) ?>
<fieldset>
    <legend><?= __('Batch Search') ?></legend>
    <ul class="fieldlist">
        <li  class="row label-left">
            <div class="col-md-6 col-lg-4">
                <?= $this->Form->input('start_date',['type' => 'text','id'=>'tbStartDate', 'required' => true, 'label'=>'From', 'value'=>date('Y-m-d', strtotime('-14 days')) ]) ?>
            </div>
            <div class="col-md-6 col-lg-4">
                <?= $this->Form->input('end_date',['type' => 'text','id'=>'tbEndDate', 'required' => true,'label'=>'To', 'value'=>date('Y-m-d') ]) ?>
            </div>
        </li>
        <li  class="row label-left">
            <div class="col-md-6 col-lg-6">
            <label>Merchants</label>
            <?= $this->Form->select('merchant_id', $merchantgroup_lst, [ 'data-placeholder'=>'Select Merchants...','empty'=>'All' ,'id'=>'cbMerchants' ,'required'=>!true,'label'=>'Merchants']) ?>
            </div>
        </li>
        <li  class="row label-left">
            <div class="col-md-6 col-lg-6">
            <label>Status</label>
            <?= $this->Form->select('state', ['OPEN'=>'Open','SETTLED'=>'Settled'], ['data-placeholder'=>'Select status...' ,'empty'=>'All' ,'id'=>'cbStates' ,'required'=>false]) ?>
            </div>
        </li>
        
    </ul>
</fieldset>
<div class="btn-wrap">
<?= $this->Form->button(__('Search'), ['type' => 'submit', 'class'=>'left  k-button']); ?>
<button type="button" class="btn-download k-button">Download</button>
</div>

<?= $this->Form->end() ?>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          
<div class="clearfix"></div>


<div id="grid"></div>                                                      
<div class="clearfix"></div>



<?=$this->Html->script('queuejob')?>
<script>

startQueueJob.cancelUrl = '<?=$this->Url->build(['controller'=>'QueueJob','action'=>'cancel'])?>';
startQueueJob.checkUrl = '<?=$this->Url->build(['controller'=>'QueueJob','action'=>'check'])?>';
$(function() {
    var startDate, endDate;
    var requestColumnData = false;
    var downloadBatchList = function() {

        $.post ( '<?= $this->Url->build(['action'=>'downloadBatches', ])?>', queryData(), function(rst){
            if(rst.status == 'done'){
                if(rst.url){
                    window.location.href = rst.url;
                }
            }
        },'json').error(function(){
            alert('Server cannot handle your request. Please try again later.');
        });
    }
    var queryData = function(){

        var formData = {
            state: statesDd ? statesDd.value() : null,
            merchant_id: merchantsDd ? merchantsDd.value() : null,
            start_date_ts: startDate.getTime(), 
            end_date_ts: endDate.getTime()
        };


    <?php if(isset($debug) && $debug == 'yes'):?>
        formData.debug = 'yes';
    <?php endif;?>
        
        return formData;
    };

    var dataSource = new kendo.data.DataSource({
        serverFiltering: false,
        transport: {
            read: {
                url:'<?=$this->Url->build([ "action" => "search"])?>',
                dataType: "json",
                data: queryData,
                type: 'post',
            },  
        },
        
        error: function (e) {
            alert('Sorry, server does not response valid result.\r\n\r\nPlease check the network connection or change the date range into smaller');
            console.log(e);
            //console.log('change');
            /*
            console.log('all_log_set:'+data.items[0].all_log_set);
            $('#log_ready').val(data.items[0].all_log_set);
            onTBChange();
            */
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                id: 'batch_id',
                fields: {
                    report_date: { type: "date", format: 'Y-m-d' },
                    from_date: { type: "date", format: 'Y-m-d' },
                    to_date: { type: "date", format: 'Y-m-d' },
                    // transaction_time: { type: "date", format: 'Y-m-d H:i:s' },
                    // net_amount: { type: "number" },
                    total_settlement_amount: { type: "number" },
                    // charge: { type: "number" },
                    // convert_amount: { type: "number" },
                    // net_amount_processor: { type: "number" },
                    // processor_fee: {type: "number"},
                }
            }
        },
        fiterable: false,
        pageSize: 25,
        sortable: false,
        serverPaging: true,
        serverSorting: false,
        sort: { field: "process_time", dir: "desc" }
    });


    var columns = [
        {
            field: 'batch_id',
            title: 'ID',
            width: 320,
            fiterable: false,
        },
        {
            field: 'report_date',
            title: 'Report Date',
            width: 120,
            fiterable: false,
                template: '#= kendo.toString(kendo.parseDate(report_date), "yyyy/MM/dd")#' ,
        },
        {
            field: 'from_date',
            title: 'Start Date',
            width: 120,
            fiterable: false,
                template: '#= kendo.toString(kendo.parseDate(from_date), "yyyy/MM/dd")#' ,
        },
        {
            field: 'to_date',
            title: 'End Date',
            width: 120,
            fiterable: false,
                template: '#= kendo.toString(kendo.parseDate(to_date), "yyyy/MM/dd")#' ,
        },
        {
            field: 'merchantgroup_name',
            title: 'Merchant',
            width: 280,
            fiterable: false,
        },
        {
            field: 'settlement_currency',
            title: 'Currency',
            width: 80,
            fiterable: false,
        },
        {
            field: 'total_settlement_amount',
            title: 'Total Settlement Amount',
            width: 180,
            fiterable: false,
            format: "{0:n2}", attributes:{style:"text-align:right;"} ,
        },
        {
            field: 'state',
            title: 'Status',
            width: 120,
            fiterable: false,
        },
        {
            title: "Actions",
            width: 100,
            sortable: false,
            filterable: false,
            template: "<a class='btn-view-batch' href=\"<?=$this->Url->build([ "action" => "view" ])?>/${batch_id}\" data-id='${batch_id}'>View</a>"
        },
        {}
    ];

    $("#grid").kendoGrid({
        columns: columns,
        dataSource: dataSource,
        selectable: "row",
        noRecords: true,
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        height:600,
        autoBind: false,
        filterable: false,
        pageable: {
            refresh: true,
            //pageSizes: true,
            pageSizes: [10, 25, 50, 100, "All"],
            buttonCount: 4
        },
        scrollable: true,
        resizable: true,
        messages: {
            noRecords: "No record found."
        },
        //scrollable: false,
    });





    function startChange() {
        var _endDate = endDateDp.value(),
        _startDate = startDateDp.value();

        if (_startDate) {
            startDate = new Date(_startDate);
            // startDate.setDate(startDate.getDate());
            endDateDp.min(startDate);
        } else if (_endDate) {
            startDateDp.max(new Date(_endDate));
        } else {
            endDate = new Date();
            startDateDp.max(endDate);
            endDateDp.min(endDate);
        }
    }

    function endChange() {
        var _endDate = endDateDp.value(),
        _startDate = startDateDp.value();

        if (_endDate) {
            endDate = new Date(_endDate);
            // _endDate.setDate(_endDate.getDate());
            startDateDp.max(_endDate);
        } else if (_startDate) {
            endDateDp.min(_startDate);
        } else {
            endDate = new Date();
            startDateDp.max(endDate);
            endDateDp.min(endDate);
        }
    }


    var today = kendo.date.today();

    var startDateDp = $("[name=start_date]").kendoDatePicker({
        format: "yyyy-MM-dd",
        change: startChange
    }).data("kendoDatePicker");


    var endDateDp = $("[name=end_date]").kendoDatePicker({
        format: "yyyy-MM-dd",
        change: endChange
    }).data("kendoDatePicker");


    startDate = startDateDp.value();
    endDate = endDateDp.value();

    // startDateDp.max(today);
    // endDateDp.max(today);

    var merchantsDd = $("#cbMerchants").kendoDropDownList({
    }).data("kendoDropDownList")

    var statesDd = $("#cbStates").kendoDropDownList({
    }).data("kendoDropDownList")

    

    $('.users').on('submit', 'form', function(evt){
        evt.preventDefault();

        dataSource.page(1);
        // dataSource.read();
    })
    .on('click', '.btn-download', function(e){
        e.preventDefault();
        downloadBatchList();

    })  .on('click', '.btn-view-batch', function(){
        location.href = '<?=$this->Url->build([ "action" => "view" ])?>/'+$(this).data('id')
    }) 


    dataSource.page(1);
});

function popup(msg) {
    var dialog = $("#errdialog").data("kendoDialog");
    dialog.content(msg);
    dialog.open();
}


</script>
<style>

@import url(<?=$this->Url->css('wc-extra')?>);

</style>