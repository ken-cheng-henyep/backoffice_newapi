<div class="users form search-input">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>

<?= $this->Form->create(null, ['url' => ['controller' => 'SettlementTransaction', 'action' => 'search'] ]) ?>
    <fieldset>
        <legend><?= __('Transaction Search') ?></legend>
        <ul class="fieldlist">
            <li  class="row label-left">
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('start_date',['type' => 'text','id'=>'tbStartDate', 'required' => true, 'label'=>'From' ]) ?>
                </div>
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('end_date',['type' => 'text','id'=>'tbEndDate', 'required' => true,'label'=>'To' ]) ?>
                </div>
            </li>
            <!-- <li>
                <?= $this->Form->select('states', ['SALE'=>'SALE','REFUNDED'=>'REFUNDED','PARTIAL_REFUND'=>'PARTIAL_REFUND','REFUND_REVERSED'=>'REFUND_REVERSED','PARTIAL_REFUND_REVERSED'=>'PARTIAL_REFUND_REVERSED'], ['multiple'=>true, 'data-placeholder'=>'Select States...' ,'id'=>'cbStates' ,'required'=>true,'default'=>['SALE','REFUNDED','PARTIAL_REFUND','REFUND_REVERSED','PARTIAL_REFUND_REVERSED']]) ?>
            </li> -->
            <li  class="row label-left">
                <div class="col-md-6 col-lg-6">
                <label>Merchants</label>
                <?= $this->Form->select('merchantgroups', $merchantgroup_lst, [ 'data-placeholder'=>'Select Merchants...','empty'=>'All' ,'id'=>'cbMerchants' ,'required'=>!true,'label'=>'Merchants']) ?>
                </div>
                <div class="col-md-6 col-lg-6">
                <label>Settlement Status</label>
                <?= $this->Form->select('settlement_status', [ 'PENDING'=>'Pending','AVAILABLE'=>'Available','UNSETTLED'=>'Unsettled','SETTLING'=>'Settling','SETTLED'=>'Settled','WITHHELD'=>'Withheld'],['id'=>'cbSettlementStatus','multi'=>true, 'label'=>'Settlement Status','value'=>'UNSETTLED' ]) ?>
                </div>
            </li>

            <li>
            </li>
            <li  class="row">
                <div class="col-md-6 col-lg-6">
                <?= $this->Form->input('transaction_id',['type' => 'text','id'=>'tbTransaction', 'label'=>'Transaction ID' ]) ?>
                </div>
                <div class="col-md-6 col-lg-6">
                <?= $this->Form->input('merchant_ref',['type' => 'text','id'=>'tbMerchantRef', 'label'=>'Merchant Ref.' ]) ?>
                </div>
            </li>
            <li  class="row">
                <div class="col-md-6 col-lg-6">
                <?= $this->Form->input('email',['type' => 'text','id'=>'tbEmail', 'label'=>'Email' ]) ?>
                </div>
                <div class="col-md-6 col-lg-6">
                <?= $this->Form->input('customer_name',['type' => 'text','id'=>'tbCustomerName', 'label'=>'Customer Name' ]) ?>
                </div>
            </li>
        </ul>
    </fieldset>
    <div>
<?= $this->Form->button(__('Submit'), ['type' => 'submit', 'class'=>'left']); ?>
<?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'right']); ?>
    </div>

<?= $this->Form->end() ?>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          
    <div class="clearfix"></div>


    <div id="grid"></div>                                                      
    <div class="clearfix"></div>
    <button type="button" class="download-btn">Download</button>
</div>


<?=$this->Html->script('queuejob')?>
<script>

startQueueJob.cancelUrl = '<?=$this->Url->build(['controller'=>'QueueJob','action'=>'cancel'])?>';
startQueueJob.checkUrl = '<?=$this->Url->build(['controller'=>'QueueJob','action'=>'check'])?>';
$(function() {

    var requestColumnData = false;
    var queryData = function(){

        var start_date = startDateDp && startDateDp.value()? startDateDp.value().getTime() : null;
        var end_date = endDateDp && endDateDp.value() ? endDateDp.value().getTime() : null;


        // console.log(start_date, end_date);
        if(!start_date){
            start_date = (new Date($("[name=start_date]").val()) ).getTime()
        }

        if(!end_date){
            end_date = (new Date($("[name=end_date]").val()) ).getTime()
        }

        // Prevent 'NAN' sent into request
        if(isNaN(start_date))
            start_date = null;
        if(isNaN(end_date))
            end_date = null;
        // console.log(start_date, end_date);

        var formData = {
            // states: statesDd ? statesDd.value() : null,
            settlement_status: settlementStatusesDd ? settlementStatusesDd.value() : null,
            merchantgroups: merchantsDd ? merchantsDd.value() : null,
            start_date_ts: start_date, 
            end_date_ts: end_date
        };


<?php if(isset($debug) && $debug == 'yes'):?>
        formData.debug = 'yes';
<?php endif;?>

        if( $('[name=email]').val() )
            formData.email = $('[name=email]').val();

        if( $('[name=customer_name]').val() )
            formData.customer_name = $('[name=customer_name]').val();

        if( $('[name=merchant_ref]').val() )
            formData.merchant_ref = $('[name=merchant_ref]').val();

        if( $('[name=transaction_id]').val() )
            formData.transaction_id = $('[name=transaction_id]').val();

        // if( requestColumnData)
            formData['columnData'] = queryFilterableFields.join(',');
        requestColumnData = false;
        
        return formData;
    };

    var dataSource = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: {
                url:'<?=$this->Url->build([ "action" => "search"])?>',
                dataType: "json",
                data: queryData,
                type: 'post',
            },  
        },
        requestEnd: function(rst){
            if(rst && rst.response && rst.response.columnData){
                for(var key in rst.response.columnData){
                    if(typeof columnDs[ key] != 'undefined'){

                        // Update child datasource for fiterable columns.
                        columnDs[ key].data( rst.response.columnData[ key] );
                    }
                }
            }
        },
        change: function (data) {
            console.log(arguments);
            //console.log('change');
            /*
            console.log('all_log_set:'+data.items[0].all_log_set);
            $('#log_ready').val(data.items[0].all_log_set);
            onTBChange();
            */
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
                fields: {
                    processor_state_time: { type: "date", format: 'Y-m-d H:i:s' },
                    search_state_time: { type: "date", format: 'Y-m-d H:i:s' },
                    state_time: { type: "date", format: 'Y-m-d H:i:s' },
                    transaction_time: { type: "date", format: 'Y-m-d H:i:s' },
                    net_amount: { type: "number" },
                    amount: { type: "number" },
                    charge: { type: "number" },
                    convert_amount: { type: "number" },
                    net_amount_processor: { type: "number" },
                    processor_fee: {type: "number"},
                }
            }
        },
        pageSize: 25,
        sortable: true,
        serverPaging: true,
        serverSorting: true,
        sort: { field: "state_time", dir: "desc" }
    });


    var columns = <?php echo json_encode($grid_columns);?>;
    var columnDs = {};
    var queryFilterableFields = [];

    columns.forEach(function(column, idx){
        if(column.filterable && column.filterable.dataSource ){

            var ds = new kendo.data.DataSource({
                sortable: true,
                serverFiltering: false,
                serverPaging: false,
                serverSorting: false,
                data: column.filterable.dataSource
            });
            columnDs [ column.field ] = ds;

            // Mark down which columns are requested for dynamic filterable column
            if(column.dataSourceType == 'query'){
                queryFilterableFields.push(column.field);
            }

            // replace existing
            column.filterable.dataSource = ds;
        }
    })
    
    columns.push({
        field: "action",
        title: "Actions",
        width: 100,
        sortable: false,
        filterable: false
        //template: "<a class='' href=\"javascript:void(0);\" data-id='${id}' onclick=\"deleteItem('${id}');\">Delete</a>"
    });

    $("#grid").kendoGrid({
        columns: columns,
        // javascript:void(0);
        // data-id=${id}, data-bid=${batch_id}
        dataSource: dataSource,
        //height: 420,
        /*change: onGridChange, */
        selectable: "row",
        noRecords: true,
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        filterable: {
         extra: false
       },
        pageable: {
            refresh: true,
            //pageSizes: true,
            pageSizes: [10, 25, 50, "All"],
            buttonCount: 2
        },
        
        resizable: true,
        messages: {
            noRecords: "No record found."
        },
        //scrollable: false,
    });


    var _grid =  $('#grid').data('kendoGrid');

    // Find the Role filter menu.
    var filterMenu = _grid.thead.find("th:not(.k-hierarchy-cell,.k-group-cell):last").data("kendoFilterMenu");

    // filterMenu.form.find("div.k-filter-help-text").text("Select an item from the list:");
    // filterMenu.form.find("span.k-dropdown:first").css("display", "none");

    // // Change the text field to a dropdownlist in the Role filter menu.
    // filterMenu.form.find(".k-textbox:first")
    //     .removeClass("k-textbox")
    //     .kendoDropDownList({
    //         dataSource: new kendo.data.DataSource({
    //             data: [
    //                 { title: "Software Engineer" },
    //                 { title: "Quality Assurance Engineer" },
    //                 { title: "Team Lead" }
    //             ]
    //         }),
    //         dataTextField: "title",
    //         dataValueField: "title"
    //     });


    function startChange() {
        var startDate = startDateDp.value(),
        endDate = endDateDp.value();

        if (startDate) {
            startDate = new Date(startDate);
            startDate.setDate(startDate.getDate());
            endDateDp.min(startDate);
        } else if (endDate) {
            startDateDp.max(new Date(endDate));
        } else {
            endDate = new Date();
            startDateDp.max(endDate);
            endDateDp.min(endDate);
        }
    }

    function endChange() {
        var endDate = endDateDp.value(),
        startDate = startDateDp.value();

        if (endDate) {
            endDate = new Date(endDate);
            endDate.setDate(endDate.getDate());
            startDateDp.max(endDate);
        } else if (startDate) {
            endDateDp.min(new Date(startDate));
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

    startDateDp.max(today);
    endDateDp.max(today);

    var merchantsDd = $("#cbMerchants").kendoDropDownList({
    }).data("kendoDropDownList")

    var statesDd = $("#cbStates").kendoMultiSelect({
    }).data("kendoMultiSelect")

    var settlementStatusesDd = $("#cbSettlementStatus").kendoMultiSelect({
    }).data("kendoMultiSelect")

    // Getting last settlement date by querying holiday
    $.getJSON('<?=$this->Url->build([ "controller"=>"Holidays", "action" => "lastBusinessDateFromDate" ])?>', function(rst){
        if(!rst.allowed){
            console.error('Cannot assign start / end date');
            console.log(rst);
        }else{
            $("[name=start_date]").attr('value', rst.range_start.date);
            $("[name=end_date]").attr('value', rst.range_end.date);
            
            startDateDp.value(  new Date(rst.range_start.date)  );
            startChange()
            endDateDp.value(  new Date(rst.range_end.date)  );
            endChange();
        }
    })

    $('.users.form form').on('submit', function(evt){
        evt.preventDefault();

        // Update the flag for asking column data
        requestColumnData = true;

        // Hack: reset all filterable selection
        $("form.k-filter-menu button[type='reset']").trigger("click");

        dataSource.page(1);
        // dataSource.read();
    })

    $('.users.form .download-btn').on('click', function(){


        startQueueJob('<?=$this->Url->build([ "action" => "queueMerchantExport" ])?>', queryData() , dataSource.total() )
    })
});

function popup(msg) {
    var dialog = $("#errdialog").data("kendoDialog");
    dialog.content(msg);
    dialog.open();
}

function deleteItem(id){
 //url = 'update-status';
    url = '<?=$this->Url->build([ "action" => "delete" ])?>'
    console.log("deleteItem("+id+") url:"+url);
    //return true;

    $.post(url,{'id':id}, function(rst) {
        if (rst.status == 'done') {
            //success
            $('#grid').data('kendoGrid').dataSource.read();
            //$('#GridName').data('kendoGrid').refresh();
            return true;
        }
    }, 'json');

    console.log('end post');
    return false;
}

</script>
<style>

div.search-input .fieldlist label{
    float: left;
    display: block;
    line-height: 2em;
    vertical-align: middle;
    width: 40%;
}
div.search-input .fieldlist .label-left label{
    padding-bottom: 0;
}
div.search-input .fieldlist .k-widget{
    float: left;
    display: block;
    line-height: 2em;
    vertical-align: middle;
    width: 59%;
}
.k-multiselect-wrap li{padding-bottom: .1em;}

#grid > .k-grid-header > div > table,
#grid > .k-grid-content > table
{
    width: 100% !important;
}
#grid > .k-grid-content {
    height: 300px;
}

*{box-sizing: border-box;}
.row{clear:both;margin-left:-15px; margin-right:-15px;position: relative; width:inherit;}
.row:after{display:block; clear: both; height: 1px; visibility: hidden; content: " ";}

 .row .col-xs-1, .row .col-xs-2, .row .col-xs-3, .row .col-xs-4, .row .col-xs-5, .row .col-xs-6, .row .col-xs-7, .row .col-xs-8, .row .col-xs-9, .row .col-xs-10, .row .col-xs-11, .row .col-xs-12
,.row .col-sm-1, .row .col-sm-2, .row .col-sm-3, .row .col-sm-4, .row .col-sm-5, .row .col-sm-6, .row .col-sm-7, .row .col-sm-8, .row .col-sm-9, .row .col-sm-10, .row .col-sm-11, .row .col-sm-12
,.row .col-md-1, .row .col-md-2, .row .col-md-3, .row .col-md-4, .row .col-md-5, .row .col-md-6, .row .col-md-7, .row .col-md-8, .row .col-md-9, .row .col-md-10, .row .col-md-11, .row .col-md-12
,.row .col-lg-1, .row .col-lg-2, .row .col-lg-3, .row .col-lg-4, .row .col-lg-5, .row .col-lg-6, .row .col-lg-7, .row .col-lg-8, .row .col-lg-9, .row .col-lg-10, .row .col-lg-11, .row .col-lg-12
{float: left; padding-left:15px;padding-right:15px;box-sizing: border-box; position: relative;min-height: 1px; width:100%; margin-bottom: 15px; }

.fieldlist li.row{padding-bottom: 0;}
.fieldlist li.row input,textarea{margin-bottom: 0;}

.row .col-xs-3{width:25%;}
.row .col-xs-4{width:33.3333%; }
.row .col-xs-5{width:41.6666%;}
.row .col-xs-6{width:50%;}
@media screen and (min-width:480px){
.row .col-sm-3{width:25%;}
.row .col-sm-4{width:33.3333%;}
.row .col-sm-5{width:41.6666%;}
.row .col-sm-6{width:50%;}
}
@media screen and (min-width:768px){
.row .col-md-3{width:25%;}
.row .col-md-4{width:33.3333%;}
.row .col-md-5{width:41.6666%;}
.row .col-md-6{width:50%;}
}
@media screen and (min-width:1200px){
.row .col-lg-3{width:25%;}
.row .col-lg-4{width:33.3333%;}
.row .col-lg-5{width:41.6666%;}
.row .col-lg-6{width:50%;}
}


</style>
<style>

.hidden{display:none; visibility: hidden;}
</style>
