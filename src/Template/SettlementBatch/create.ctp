<div class="form search-input">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>

<?= $this->Form->create(null, ['url' => ['action' => 'processSubmit'] , 'name' => 'processSubmit']) ?>
    <fieldset>
        <legend><?= __('New Batch - Select Transaction') ?></legend>
        <ul class="fieldlist">
            <li  class="row label-left">
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('report_date',['type' => 'text','id'=>'tbReportDate', 'label'=>'Report Date:' ]) ?>
                </div>
            </li>
            <li  class="row label-left">
                <div class="col-md-6 col-lg-4">
                    <label>Merchant:</label>
                    <div class="input"><span data-field="merchantgroup_name"><?php echo $masterMerchant['name']?></span></div>
                </div>
            </li>
            <li  class="row label-left">
                <div class="col-md-6 col-lg-4">
                    <label>Status:</label>
                    <div class="input"><span data-field="batch_status">Open</span></div>
                </div>
            </li>
        </ul>
    </fieldset>

    <div class="btn-wrap">
        <div class="left" style="margin-top:6px; margin-right:8px;">A: Select from List: </div>
    </div>
    <div id="grid-transaction"></div>
    <div class="clearfix"></div>
    <div class="btn-wrap" >
        <div class="left" style="margin-top:6px; margin-right:8px;">B: Specific Amount to Settle (<?php echo $masterMerchant['settle_currency']?>): </div>
<div class="left" style=" margin-right:8px;">
    <input type="number" class="tf-target-amount" />
</div>
<?= $this->Form->button(__('Select'), ['type' => 'button', 'class'=>'left btn-reselect-by-amount k-button']); ?>
    </div>
    <div class="clearfix"></div>
    <div class="btn-wrap" style="margin-bottom: 0;">
        <div class="inline">
        <div class="text">C: Select Transaction Date: </div>
        <?= $this->Form->input('start_date_ntx',['type' => 'text','id'=>'tbStartDateNtx', 'label'=>'From:' ]) ?>
        <?= $this->Form->input('end_date_ntx',['type' => 'text','id'=>'tbEndDateNtx', 'label'=>'To:' ]) ?></div>
       
<?= $this->Form->button(__('Select'), ['type' => 'button', 'class'=>'left btn-reselect-by-date k-button']); ?>
    </div>
    <div style="margin-top:0; margin-bottom:10px;">
        <div class="content-block">Summary:</div>
        <div class="content-block">Transaction Date: <span class="lblStartDate"><?php echo date('Y-m-d', strtotime($startDate))?></span> to <span class="lblEndDate"><?php echo date('Y-m-d', strtotime($endDate))?></span></div>
        <div class="content-block">Settlement Currency: <?php echo $masterMerchant['settle_currency']?></div>
        <div class="content-block">Settlement FX package: <?php echo $masterMerchant['settle_option']?></div>
<?php if($masterMerchant['settle_option'] == '2'){?>
        <div class="content-block">Settlement FX Rate: <?php echo number_format($masterMerchant['daily_fxrate'],4)?></div>
<?php } ?>
    <div id="grid-particulars"></div>
    <div class="clearfix"></div>
        <div class="content-block"><span class="lblFooterMessage"></span></div>
    <div class="clearfix"></div>
    <div class="btn-wrap">
<?= $this->Form->button(__('Re-calc'), ['type' => 'button', 'class'=>'left btn-recal-summary k-button']); ?>
<?= $this->Form->button(__('Reload'), ['type' => 'button', 'class'=>'left btn-reload-summary k-button']); ?>
<?= $this->Form->button(__('Preview'), ['type' => 'button', 'class'=>'left btn-download-batch k-button']); ?>
<?= $this->Form->button(__('Process'), ['type' => 'button', 'class'=>'left btn-submit-batch k-button']); ?>
    </div>
    <div>
    </div>

<?= $this->Form->end() ?>

<?=$this->Html->script('queuejob')?>
<script>

startQueueJob.cancelUrl = '<?=$this->Url->build(['controller'=>'QueueJob','action'=>'cancel'])?>';
startQueueJob.checkUrl = '<?=$this->Url->build(['controller'=>'QueueJob','action'=>'check'])?>';
$(function(){


    var merchantGroupId = '<?php echo $masterMerchant['merchantgroup_id'] ?>';
    var merchantId = '<?php echo $masterMerchant['id'] ?>';
    var merchantCurrency  = '<?php echo $merchantCurrency?>';
    var defaultCurrency  = '<?php echo $defaultCurrency?>';
    var merchantBankAccount = 'N/A';
    var merchantBankName = 'N/A';
    var startDate = '<?php echo $startDate?>';
    var endDate = '<?php echo $endDate?>';
    var selectedTxids = [];
    var selectedTxidMap = {};
    var isNonTxMode = false;
    var endDateNtx = null, startDateNtx = null;
    var loadedChecksum = '<?php echo $localChecksum?>';
    var loadedChecksumChanged = false;
    var isNtxDateChanged = false;

    var origStartDate = '<?php echo $startDate?>';
    var origEndDate = '<?php echo $endDate?>';
    var isSelectedAll = true;

    var selectedTxidMap = {};
    var totalTx = 0;
    var isTxQueried = false;
    var allTxDs = [];

    function showDetailsGrid(e, particular, currency, detailPostData, model, columns, detailInit, detailExpand, detailCollapse, dataBound, preferredHeight ) {

        if(!preferredHeight) preferredHeight = 420;
        var cfg = {
            dataSource: {
                type: "json",
                transport: {
                    read: {
                        url: "<?=$this->Url->build(['action'=>'fetchMerchantDetail', $masterMerchant['id'] ])?>",
                        type: 'post',
                        data: function(origData){
                            var postData = createBatchPostData(origData);
                            postData.particular = particular 
                            postData.currency = currency;

                            if(detailPostData){
                                if(typeof detailPostData == 'function'){
                                    detailPostData = detailPostData();
                                }

                                if(typeof detailPostData == 'object'){
                                    for(var key in detailPostData)
                                        postData[key] = detailPostData[key];
                                }
                            }
                            return postData;
                        }
                    }
                },
                serverPaging: true,
                serverSorting: false,
                serverFiltering: false,
                pageSize: 10,
                // describe the result format
                schema: {
                    // the data, which the data source will be bound to is in the "list" field of the response
                    data: function(response) {
                        return response.data; 
                    },
                    total: function(response) {
                        return response.total; // total is returned in the "total" field of the response
                    },
                    model: model
                },
            },
            detailInit: detailInit,
            dataBound: dataBound,
            detailExpand: detailExpand,
            detailCollapse: detailCollapse,
            noRecords: true,
            messages: {
                noRecords: ('No record found.')
            },
            scrollable: true,
            sortable: false,
            height: preferredHeight,
            pageable: {
                refresh: true,
                //pageSizes: true,
                pageSizes: [10, 25, 50, 100],
            },
            columns: columns,
        };

        $("<div/>").appendTo(e.detailCell).kendoGrid(cfg);
    }
    function showPaymentTransaction(e, particular, currency, postData) {
        showDetailsGrid(e, particular, currency, postData,{
            id: "id",
            fields: {
                "tx_convert_amount": {
                    type:"number"
                },
                "converted_fee": {
                    type:"number"
                },
                "converted_amount": {
                    type:"number"
                }
            }
        } , [
            { field: "reconciled_state_time", title:"Date", width: 120, template: function(entry){
                return kendo.toString(new Date(entry.reconciled_state_time), 'yyyy-MM-dd')
            } },
            { field: "state", title:"Trans Type", width: 120 },
            { field: "customer_name", title:"Customer", width: 240 },
            { field: "merchant", title: "Account", width: 240  },
            { field: "tx_currency", title: "P. Currency", width: 120 },
            { field: "tx_amount", title: "Amount", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "tx_fee", title: "Fee", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "tx_net_amount", title: "Net Amount", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "settle_currency", title: "M. Currency", width: 120 },
            { field: "convert_rate", title: "FX Rate", width: 120, format: "{0:n4}", attributes:{style:'text-align:right'} },
            { field: "tx_convert_amount", title: "Converted Amount", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "merchant_ref", title: "Merchant Ref", width: 240, sortable: false },
            { field: "transaction_id", title: "Transaction ID", width: 320 },
            { }
                
        ])
        
    }
    function showBatchRemittance(e, particular, currency) {
        
        showDetailsGrid(e, 'batchRemittance', currency, {},{
            id: "id",
            fields: {
                "count": {
                    type:"number"
                },
                "amount": {
                    type:"number"
                },
                "convert_amount": {
                    type:"number"
                }
            }
        },[
            { field: "id", title:"Batch ID", width: 120, },
            { field: "upload_time", title:"Upload Time", width: 200 },
            { field: "complete_time", title:"Complete Time", width: 200 },
            { field: "count", title: "Count", width: 100, format: "{0:n0}", attributes:{style:"text-align:right;" }},
            { field: "convert_currency", title: "Currency", width: 100 },
            { field: "amount", title: "Amount", width: 120, format: "{0:n2}", attributes:{style:"text-align:right;" }},
            { field: "convert_amount", title: "Converted Amount", width: 120, format: "{0:n2}", attributes:{style:"text-align:right;" }},
            { field: "target_name", title: "Channel", width:320 },
            { title: 'Actions', sortable: false, width:240, filterable: false, template: function(entity){
                    return $('<a href="javascript:void(0);" class="btn-view-subgrid"><span class="hidden-expanded">View</span><span class="hidden-unexpanded">Close</span></a><a href="javascript:void(0);" class="btn-close-subgrid hidden" p-id>Close</a>')
                        .attr('data-id', entity.id)
                        .attr('data-particular', 'batchRemitannceTx')
                        .attr('data-currency', entity.currency).get(0).outerHTML
                return '';
            } },
            {}
        ], 
        function(e) {
            var $masterRow = e.masterRow;
            showBatchRemittanceDetail(e, 'batchRemittanceTx', currency, e.data.id );
        }, null, null, function(){
            
            var grid = this;
            var view = this.dataSource.view();
            
            // Select all selected items during paging data loaded/binded to rows
            for(var idx = 0; idx < view.length; idx ++){
                var dataItem = view[idx];

                var $tr = grid.tbody.find("tr[data-uid='" + dataItem.uid + "']");
                $tr.find('.k-hierarchy-cell > .k-i-expand').hide(); // hack: hiding the left button to keep expand-row functions

            }
        });
    }
    
    function showBatchRemittanceDetail(e, particular, currency, rBatchId) {
        showDetailsGrid(e, 'batchRemittanceTx', currency, {rBatchId: rBatchId},{
            id: "id",
            fields: {
                "amount": {
                    type:"number"
                },
                "convert_amount": {
                    type:"number"
                },
                "convert_paid_amount": {
                    type:"number"
                },
                "convert_rate": {
                    type:"number"
                }
            }
        },[
            { field: "index", title:"ID", width: 80 },
            { field: "account", title:"Account No.", width: 180 },
            { field: "beneficiary_name", title: "Name", width: 120}, 
            { field: "bank_name", title: "Bank Name", width: 240 },
            { field: "bank_branch", title: "Bank Branch", width: 240 },
            { field: "province", title: "Province", width: 120 },
            { field: "city", title: "City", width: 120 },
            { field: "id_number", title: "ID Card", width: 240 },
            { field: "amount", title: "CNY", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "convert_currency", title: "Currency", width: 120 },
            { field: "convert_paid_amount", title: "Converted Amount", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "convert_rate_display", title: "Rate", width: 120, format: "{0:n4}", attributes:{style:'text-align:right'} },
            { field: "merchant_ref", title: "Ref." , width: 240},
            { },
        ], null, null, null, null, 240);
    }

    function showBatchRemittanceAdjustment(e, particular, currency) {
        showDetailsGrid(e, particular, currency, {},{
            id: "id",
            fields: {
                "amount": {
                    type:"number"
                },
                "converted_amount": {
                    type:"number"
                },
                "convert_rate": {
                    type:"number"
                }
            }
        },[
            { field: "tx_time", title:"Date", width: 180 },
            { field: "id", title:"Reference ID", width: 120  },
            { field: "amount", title:"Adjustment CNY", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "currency", title:"Currency", width: 100 },
            { field: "converted_amount", title: "Converted Amount", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "convert_rate", title: "Rate", width: 120, format: "{0:n4}", attributes:{style:'text-align:right'} },
            {  },
        ]);
    }

    function showInstantRemittance(e, particular, currency) {
        showDetailsGrid(e, 'instantRemittance', currency, {},{
            id: "id",
            fields: {
                "count": {
                    type:"number"
                },
                "converted_amount": {
                    type:"number"
                }
            }
        },[
            { field: "ir_time", title:"Tx Time", width: 180 },
            { field: "name", title:"Name", width: 240},
            { field: "account", title:"Account", width: 240 },
            { field: "bank_name", title:"Bank Name", width: 240 },
            { field: "bank_branch", title:"Bank Branch", width: 240 },
            { field: "province", title:"Province", width: 120 },
            { field: "city", title:"City", width: 120 },
            { field: "id_number", title: "ID Card", width: 240 },
            { field: "amount", title:"CNY", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "gross_amount", title:"Client Received", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "gross_amount", title:"Gross Amount", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },

            { field: "fee", title:"Fee", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "paid_amount", title:"Paid Amount", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'}  },
            { field: "convert_currency", title:"Currency", width: 120 },
            { field: "converted_amount", title:"Converted Amount", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'}  },
            { field: "convert_rate", title:"Rate", width: 100, format: "{0:n4}", attributes:{style:'text-align:right'}  },
            { field: "merchant_ref", title:"Reference", width: 240 },

            { field: "id_type", title:"ID Card Type", width: 120 },
            { field: "ir_id", title:"Trans ID", width: 320 },
            { field: "target_name", title: "Processor", width: 120 },
            {}
        ]);
    }

    function showInstantRemittanceAdjustment(e, particular, currency) {
        showDetailsGrid(e, 'instantRemittanceAdj', currency, {},{
            id: "id",
            fields: {
                "amount": {
                    type:"number"
                },
                "rate": {
                    type:"number"
                },
                "converted_amount": {
                    type:"number"
                }
            }
        },[
            { field: "ir_time", title:"Date", width:120 },
            { field: "id", title:"Trans ID", width:120 },
            { field: "amount", title:"Adjustment CNY", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "convert_currency", title:"Currency", width:120 },
            { field: "converted_amount", title: "Converted Amount", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "convert_rate", title: "Rate", width:120, format: "{0:n4}", attributes:{style:'text-align:right'} },
            {}
        ]);

    }


    var $self = $('body');

    var $activityIndicator = $('<div class="k-loading-mask fixed"><span class="k-loading-text">Loading...</span><div class="k-loading-image"><div class="k-loading-color"></div></div></div>');

    /**
     * Shows the indicator.
     */
    function showIndicator()
    {
        $('.search-input.form').css('position','relative');
        $activityIndicator.appendTo('.search-input.form');
    }

    /**
     * Hides the indicator.
     */
    function hideIndicator()
    {
        $activityIndicator.remove();
    }

    var txDateDs = new kendo.data.DataSource({
        sortable: true,
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
    });

    var txColumns = [
        {
            selectable: true, 
            width: 50,
            sortable: false,
            filterable: false,
            headerTemplate: function(){
                var instanceId = 'cb_'+((Math.random()*1000000)<<0)+'_all';
                return '<input type="checkbox" class="checkbox k-checkbox" data-select="all" id="'+instanceId+'" /><label for="'+instanceId+'" class="k-checkbox-label k-no-text"></label>'
            },
            template: function(dataItem){
                var foundIndex = $.inArray(dataItem.id, selectedTxidMap);
                var instanceId = 'cb_'+((Math.random()*1000000)<<0)+'_'+dataItem.id;
                var checkedFlag = foundIndex < 0 ? '' : ' checked=""';
                return '<input type="checkbox" class="checkbox k-checkbox"'+checkedFlag+' value="'+dataItem.id+'" id="'+instanceId+'"/><label for="'+instanceId+'" class="k-checkbox-label k-no-text"></label>';
            }

        },
        { field: "reconciled_state_time", title:"Date", width: 120, template: function(entry){
            return kendo.toString(new Date(entry.reconciled_state_time), 'yyyy-MM-dd')
        },
         sortable: true, filterable: {
            dataSource: txDateDs,
            'multi': true, 'checkAll': true, 
        }},
        {field:'state',title:'Trans Type', width: 100, sortable: true, filterable: false},
        {field:'customer_name',title:'Customer', width: 200, sortable: true, filterable: false},
        {field:'merchant',title:'Account', width: 320, sortable: true, filterable: false},
        // {field:'email',title:'Email', width: 280, sortable: true, filterable: true},
        // {field:'merchant',title:'Account', width: 320, sortable: true, filterable: false},
        // {field:'processor_name',title:'Processor', width: 100, sortable: true, filterable: false},
        {field:'tx_amount',title:'Amount', width: 180, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:'text-align:right'}},
        {field:'tx_fee',title:'Fee', width: 100, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:'text-align:right'}},
        {field:'tx_net_amount',title:'Net Amount', width: 180, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:'text-align:right'}},

        {field:'tx_currency',title:'Currency', width: 100, sortable: true, filterable: false},
        {field:'convert_rate',title:'Convert Rate', width: 100, sortable: false, filterable: false, format: "{0:n4}", attributes:{style:'text-align:right'}},
        
        {field:'tx_convert_amount',title:'Converted Amount', width: 180, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:'text-align:right'}},
        {field:'merchant_ref',title:'Merchant Ref', width: 320, sortable: false, filterable: false},
        {field:'transaction_id',title:'Transaction Id', width: 320, sortable: false, filterable: false},
        // {field:'product',title:'Product', width: 200, sortable: true, filterable: false},
        // {field:'ip_address',title:'IP Address', width: 180, sortable: true, filterable: false}
    ];

    var reloadDateRange = function()
    {
        // console.log('reloadDateRange','before',startDate, endDate, 'isNonTxMode:',isNonTxMode? 'y':'n', 'isSelectedAll',isSelectedAll?'y':'n');

        if(!isNonTxMode) {
            if(isSelectedAll){
                startDate = origStartDate;
                endDate = origEndDate;
            }else{
                startDate = origStartDate;
                endDate = origEndDate;

                var startDateObj = null;
                var endDateObj = null;

                for(var txid in selectedTxidMap){
                    var tx = txAllDs.get(txid);
                    var txDate = new Date(tx.state_date);
                    
                    if(startDateObj == null || txDate.getTime() < startDateObj.getTime()) {
                        startDateObj = txDate;
                    }
                    if(endDateObj == null || txDate.getTime() > endDateObj.getTime()) {
                        endDateObj = txDate;
                    }
                }
                
                if( startDateObj && endDateObj) {
                    startDate = kendo.toString(startDateObj, "yyyy-MM-dd");
                    endDate = kendo.toString(endDateObj, "yyyy-MM-dd");
                }
            } 
        }else{
            updateNtxDate();
        }
        if(startDate == null){
            startDate = origStartDate;
        }
        if(endDate == null){
            endDate = origEndDate;
        }

        updateDateRange();
        // console.log('reloadDateRange','after',startDate, endDate, 'isNonTxMode:',isNonTxMode? 'y':'n', 'isSelectedAll',isSelectedAll?'y':'n');
    }

    var updateDateRange = function(_startDate, _endDate) {
        if(_startDate)
            startDate = _startDate;
        if(_endDate)
            endDate = _endDate;
        console.log('updateDateRange', startDate, endDate);
        if(startDate != null && endDate != null) {
            $('.lblStartDate').text( startDate)
            $('.lblEndDate').text( endDate)
            startDateNtxDp.max (endDate)
            startDateNtxDp.value (startDate)
            endDateNtxDp.min(startDate)
            endDateNtxDp.value(endDate);
        }else{
            $('.lblStartDate').text('-')
            $('.lblEndDate').text('-')

        }
    }
    var fetchSummary = function(callback, clearCache) 
    {

        clearCache = clearCache !== true ? false :true

        var url = "<?=$this->Url->build(['action'=>'fetchMerchant', $masterMerchant['id'] ])?>"
        var postData = createBatchPostData();

        // Tell server that how many sales tx selected.
        if(isTxQueried && !isSelectedAll){
            postData.txid = selectedTxids.join(',');
        }

        // Tell server that hows much of the broughtForward.
        var broughtForward = getParticularData('broughtForward', merchantCurrency);
        if(broughtForward ){
            postData['particulars[broughtForward-'+merchantCurrency+']'] = {
                converted_amount: broughtForward.converted_amount,
                remarks: broughtForward.remarks,
            }
        }
        // Tell server that hows much of the carryforword.
        var carryForward = getParticularData('carryForward', merchantCurrency);
        if(carryForward ){
            postData['particulars[carryForward-'+merchantCurrency+']'] = {
                converted_amount: carryForward.converted_amount,
                remarks: carryForward.remarks,
            }
        }
        // Tell server that hows much of the adhoc adj.
        var adhocAdj = getParticularData('adhocAdj', merchantCurrency);
        if(adhocAdj ){
            postData['particulars[adhocAdj-'+merchantCurrency+']'] = {
                converted_amount: adhocAdj.converted_amount,
                remarks: adhocAdj.remarks,
            }
        }
        // Tell server that hows much of the footnotes
        var footnotes = getParticularData('footnotes');
        if(footnotes ){
            postData['particulars[footnotes]'] = {
                remarks: footnotes.remarks,
            }
        }
        // Tell server that hows much of the totalSettlementAmount in client side
        var totalSettlementAmount = getParticularData('totalSettlementAmount', merchantCurrency);
        if(totalSettlementAmount ){
            postData['particulars[totalSettlementAmount]'] = {
                converted_amount: totalSettlementAmount.converted_amount,
            }
        }

        // Send to browser if the cache if need to clear 
        if(clearCache) {
            postData['clearCache'] = 'yes';
        }

        $.post(url, postData, function(rst){

            if(rst.merchant) {
                if(rst.merchant.settleBankAccount) {
                    merchantBankAccount = rst.merchant.settleBankAccount;
                    merchantBankName = rst.merchant.settleBankName;
                }
            }

            if(rst.checksum){
                loadedChecksum = rst.checksum;
                loadedChecksumChanged = false;
            }
            
            for(var key in rst.particulars){
                var updated = rst.particulars[key];

                // If the particular is missing
                if (typeof particularsIndexing[ key ] == 'undefined') {
                    continue;
                }

                var offset = particularsIndexing[ key ] ;
                var particularsPart = particulars [ offset ];

                if (typeof particularsPart.amount != 'undefined') {
                    particularsPart.amount = updated.amount;
                }
                if (typeof particularsPart.converted_amount != 'undefined') {
                    particularsPart.converted_amount = updated.converted_amount;
                }
            }

            updateButton();
            updateFooterMessage();


            particularsDs.data( particulars );

            if(callback) callback(null);
        }, 'json').error( function(e){
            
            if(callback) callback(e);
            alert('Cannot reload particulars data. Please try again later.')
        })
    }

    var footerMessageTpl = kendo.template('The net settlement amount #=currency# #=convertedAmount# has been remitted to your corporate account no. #=bankAccount# held at #=bankName# on #=reportDate#.')
            
    var updateFooterMessage = function(){
        
        $('.lblFooterMessage').text('');

        var totalSettlementAmountPart = getParticularData('totalSettlementAmount', '<?php echo $merchantCurrency?>');
        if(totalSettlementAmountPart.converted_amount && totalSettlementAmountPart.converted_amount > 0){
            $('.lblFooterMessage').text(footerMessageTpl({
                'currency':'<?php echo $merchantCurrency?>',
                'convertedAmount': kendo.format('{0:n2}',totalSettlementAmountPart.converted_amount),
                'bankAccount':merchantBankAccount,
                'bankName': merchantBankName,
                'reportDate': kendo.toString(new Date($('#tbReportDate').val()), "MMMM d, yyyy"),
            }));
        }
    }

    var updateButton = function(){

        var totalAmount = getParticularData('totalSettlementAmount', '<?php echo $merchantCurrency?>') .converted_amount;
        var specifiedAmount = $('.tf-target-amount').val();
        
        // Disable process button if the total amount is lower than zero 
        var isTotalLessThanZero = totalAmount < 0;

        var isTotalLessThanRequested = false;
        if(specifiedAmount != null && specifiedAmount.length > 0){
            if( totalAmount < parseFloat(specifiedAmount) ) {
                isTotalLessThanRequested = true;
            }
        } 

        $('.btn-submit-batch').prop('disabled', isTotalLessThanZero || isTotalLessThanRequested);

    }
    var txAllDs = new kendo.data.DataSource({
        paging: false,
        transport: {
            read: {
                url:"<?=$this->Url->build(['action'=>'fetchInitialMerchant', $masterMerchant['id'] ])?>",
                data: function(config){
                    var postData = createBatchPostData(config);
                    return postData;
                },
                dataType: 'json',
                type:"post",
            }
        },
        // describe the result format
        schema: {
            // the data, which the data source will be bound to is in the "list" field of the response
            data: "data",
            total: "total",
            model: {
                id: 'id',
            }
        },
        model: {
            id: "id",
            fields: {
                id: {
                    //this field will not be editable (default value is true)
                    editable: false,
                    // a defaultValue will not be assigned (default value is false)
                    nullable: true
                },

            }
        }
    })

    // Used to show the current page of the transaction sales
    var txDs = new kendo.data.DataSource({
        paging: true,
        pageSize: 25,
        sortable: true,
        transport: {
            read: {
                url:"<?=$this->Url->build(['action'=>'fetchMerchantDetail', $masterMerchant['id'] ])?>",
                data: function(config){
                    var postData = createBatchPostData(config);
                    postData.particular = 'sales'
                    postData.start = origStartDate;
                    postData.end = origEndDate;
                    var _data = txAllDs.data();
                    var _txids = [];
                    for(var i = 0; i <_data.length; i ++ ){
                        _txids.push(_data[i].id);
                    }
                    postData.txid = _txids.join(',');

                    if(postData.ntx) {
                        delete postData.ntx;
                    }
                    
                    return postData;
                },
                dataType: 'json',
                type:"post",
            }
        },
        requestEnd: function(e) {
            var response = e.response;
            var type = e.type;
            

            updateNtxDate();
            reloadDateRange();

        },
        // describe the result format
        schema: {
            // the data, which the data source will be bound to is in the "list" field of the response
            data: "data",
            total: "total",
            model: {
                id: "id",
                fields: {
                    id: {
                        //this field will not be editable (default value is true)
                        editable: false,
                        // a defaultValue will not be assigned (default value is false)
                        nullable: true
                    },
                    tx_net_amount: {
                        type: 'number'
                    },
                    tx_fee: {
                        type: 'number'
                    },
                    converted_net_amount: {
                        type: 'number'
                    }
    
                }
            }
        },
        serverFiltering: true,
        serverPaging: true,
        serverSorting: true,
        data: [],
        sort: { field: "_index", dir: "asc" },

        
    });


    var particularsColumns = [
        {field:'particular_name',title:'Particular', width: 240, sortable: false, filterable: false},
        {field:'currency',title:'Currency', width: 80, sortable: false, filterable: false},
        {field:'amount',title:'Amount', width: 180, sortable: false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {field:'converted_amount',title:'Converted Amount', width: 180, sortable: false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {field:'remarks',title:'Remarks', width: 320, sortable: false, filterable: false,  template: function(entity){
            if(!entity.remarks) return '';
            return (''+entity.remarks).replace(/[\n\r]+/g,"<br />\n");
        }},
        {title:'Actions', sortable: false, filterable: false, width:120, template: function(entity){
            if(entity.type == 'remark_change' || entity.type == 'remark_long_change' || entity.type == 'amount_remark_change'){
                return $('<a href="javascript:void(0);" class="btn-edit-remark">Edit</a>')
                    .attr('data-id', entity.id)
                    .attr('data-particular', entity.particular)
                    .attr('data-currency', entity.currency).get(0).outerHTML
            }

            if(entity.type == 'subgrid'){
                return $('<a href="javascript:void(0);" class="btn-view-subgrid"><span class="hidden-expanded">View</span><span class="hidden-unexpanded">Close</span></a><a href="javascript:void(0);" class="btn-close-subgrid hidden" p-id>Close</a>')
                    .attr('data-id', entity.id)
                    .attr('data-particular', entity.particular)
                    .attr('data-currency', entity.currency).get(0).outerHTML
            }

            return '';
        }}, 
        {}
    ];

    var particulars = <?php echo json_encode($particulars)?>;

    // Create the indexing table for particulars
    var particularsIndexing = {};
    for(var i = 0 ; i < particulars.length; i ++){
        var particularsPart = particulars[i];
        particularsIndexing[ particularsPart.id ] = i;
    }

    var particularsDs = new kendo.data.DataSource({
        schema:{
            model: {
                id: "id",
            }
        },
        data: particulars
    })

    var updateSelectAllCheckbox = function(){
        if(grid){
            $('#grid-transaction').find('[role=columnheader] .checkbox')[0].checked = grid.items().find(":checked").length == grid.dataSource.view().length && grid.dataSource.view().length > 0;
        }
    }

    var buildQueryData = function(){

        var formData = {
            
        };


<?php if(isset($debug) && $debug == 'yes'):?>
        formData.debug = 'yes';
<?php endif;?>
        return formData;
    };

    var getParticularData = function(particularName, currency)
    {
        var id = currency ?  particularName+'-'+currency : particularName;
        if(typeof particularsIndexing[ id] != 'undefined'){
            var index = particularsIndexing[ id];
            return particulars[ index ]
        }
        // console.log('getParticularData: Cannot find the details of', id, 'from', particulars)
        return null;
    }

    /**
     * A function to reselect the suggested transaction by a given amount.
     *
     * @param      integer  amount  The amount
     */
    var reselectTxByAmount = function(targetAmount)
    {
        isNtxDateChanged = false;
        var balance = 0;
        var balanceWithoutRemittance = 0;

        var broughtForward = getParticularData('broughtForward', '<?php echo $merchantCurrency?>')
        if(broughtForward && broughtForward.converted_amount){
            balance += parseFloat(broughtForward.converted_amount)
            balanceWithoutRemittance +=  parseFloat(broughtForward.converted_amount)
        }

        var refund1 = getParticularData('refund', '<?php echo $defaultCurrency?>')
        if(refund1 && refund1.converted_amount){
            balance += parseFloat(refund1.converted_amount)
            balanceWithoutRemittance +=  parseFloat(refund1.converted_amount)
        }

        var refund2 = getParticularData('refund', '<?php echo $merchantCurrency?>')
        if(refund2 && refund2.converted_amount){
            balance += parseFloat(refund2.converted_amount)
            balanceWithoutRemittance +=  parseFloat(refund2.converted_amount)
        }

        var batchRemittance = getParticularData('batchRemittance', '<?php echo $defaultCurrency?>')
        if(batchRemittance && batchRemittance.converted_amount){
            balance += parseFloat(batchRemittance.converted_amount)
            // if(isNonTxMode){
            //     balanceWithoutRemittance += parseFloat(batchRemittance.converted_amount);
            // }
        }

        var batchRemittanceAdj = getParticularData('batchRemittanceAdj', '<?php echo $defaultCurrency?>')
        if(batchRemittanceAdj && batchRemittanceAdj.converted_amount){
            balance += parseFloat(batchRemittanceAdj.converted_amount)
            // if(isNonTxMode){
            //     balanceWithoutRemittance += parseFloat(batchRemittance.converted_amount);
            // }
        }

        var instantRemittance = getParticularData('instantRemittance', '<?php echo $defaultCurrency?>')
        if(instantRemittance && instantRemittance.converted_amount){
            balance += parseFloat(instantRemittance.converted_amount)
            // if(isNonTxMode){
            //     balanceWithoutRemittance += parseFloat(batchRemittance.converted_amount);
            // }
        }

        var instantRemittanceAdj = getParticularData('instantRemittanceAdj', '<?php echo $defaultCurrency?>')
        if(instantRemittanceAdj && instantRemittanceAdj.converted_amount){
            balance += parseFloat(instantRemittanceAdj.converted_amount)
            // if(isNonTxMode){
            //     balanceWithoutRemittance += parseFloat(batchRemittance.converted_amount);
            // }
        }

        var settlementHandlingFee = getParticularData('settlementHandlingFee', '<?php echo $merchantCurrency?>')
        if(settlementHandlingFee && settlementHandlingFee.converted_amount){
            balance += parseFloat(settlementHandlingFee.converted_amount)
            balanceWithoutRemittance+= parseFloat(settlementHandlingFee.converted_amount)
        }

        // For carryForward, reset to 0 for each reselect action. 
        // Amount should be re-entered if any remain balance after tx reselection
        var carryForward = getParticularData('carryForward', '<?php echo $merchantCurrency?>')
        if(carryForward && carryForward.converted_amount){
            resetCarryForward();
        }

        var adhocAdj = getParticularData('adhocAdj', '<?php echo $merchantCurrency?>')
        if(adhocAdj && adhocAdj.converted_amount){
            balance += parseFloat(adhocAdj.converted_amount)
            balanceWithoutRemittance+= parseFloat(adhocAdj.converted_amount)
        }
        
        console.log('Current Balance: ',balance, 'Target Settle Amount:', targetAmount)
        console.log('Current balanceWithoutRemittance: ',balanceWithoutRemittance)

        // Clear checksum
        // loadedChecksum = null;
        loadedChecksumChanged = true;

        // Do not include refund and remittance in the requested amount.
        var requestedAmount = targetAmount - balanceWithoutRemittance;


        // For the balance does not meet the target
        if(balanceWithoutRemittance < targetAmount){
            var _txs = txAllDs.data();
            var totalSales = 0;
            isNonTxMode = false;

            // Descending order
            // var txs = [];
            for(var i = 0; i < _txs.length; i ++){
                var tx = _txs[i];
                // txs.push(tx);
                totalSales += tx.converted_net_amount;
            }
            // txs.sort(function(a,b){ return a.converted_net_amount - b.converted_net_amount }).reverse()
            // console.log(txs, _txs);

 
            // If the total amount  of all sales tx is lower than target, select all directly
            if(totalSales + balance < targetAmount){
                isSelectedAll = true;
                selectAllTransaction(false, function(){
                    
                    recalculateSalesTx();
                    recalculateSummary();
                    reloadDateRange();
                    // updateNtxDate();


                    carryForward.converted_amount = (targetAmount - requestedAmount) * -1;
                    carryForward.remarks = carryForward.converted_amount < 0 ? 'Amount exceeds the requested settlement amount of <?php echo $merchantCurrency?> ' + parseFloat(targetAmount) : '';

                    updateFooterMessage();
                });
                
                // fetchSummary();
            }else{
                isSelectedAll = false;
                showIndicator();

                var url = "<?=$this->Url->build(['action'=>'fetchSuggestedTx', $masterMerchant['id'] ])?>";
                
                
                $.post(url, {
                    requested_amount: requestedAmount,
                    start: origStartDate,
                    end: origEndDate,
                }, function(rst){

                    selectedTxids = rst.data;
                    selectedTxidMap = rst.map;

                    isNonTxMode = selectedTxids.length < 1;

                    reloadTxRows();
                    updateSelectAllCheckbox();
                    updateNtxDate();

                    // If the suggested tx amount is larger than requested,
                    // Move the remain to carry forward
                    if(rst.amount > requestedAmount){
                        carryForward.converted_amount = (rst.amount - requestedAmount) * -1;
                        carryForward.remarks = 'Amount exceeds the requested settlement amount of <?php echo $merchantCurrency?> ' + parseFloat(targetAmount)
                    }

                    recalculateSalesTx();
                    recalculateSummary();
                    reloadDateRange();
                    updateFooterMessage();
                    hideIndicator();
                    // fetchSummary(function(){
                    //     hideIndicator();
                    // });

                }, 'json'). error(function(){

                    hideIndicator();
                });

            }

        }else{
            console.log('No tx should be selected');
            
            isNonTxMode = true;

            carryForward.converted_amount = -1 * (balanceWithoutRemittance - targetAmount );
            if ( carryForward.converted_amount < 0) { 
                carryForward.remarks = 'Amount exceeds the requested settlement amount of <?php echo $merchantCurrency?> ' + parseFloat(targetAmount)
            } else {
                carryForward.remarks = '';
            }

            startDate = kendo.toString(earliesRange, 'yyyy-MM-dd')
            endDate = kendo.toString(yesturday, 'yyyy-MM-dd')

            console.log('No tx selected date range reset to  ', startDate, endDate)

            // Reset to non-tx date range
            updateDateRange();

            selectAllTransaction(false, function(){
                recalculateSalesTx();
            });

        }
    }

    var reselectTxByDate = function(_startDate, _endDate) {
        isSelectedAll = false;
        isNtxDateChanged = false;
        updateDateRange(_startDate, _endDate);
        showIndicator();

        var url = "<?=$this->Url->build(['action'=>'fetchSuggestedTx', $masterMerchant['id'] ])?>";
        
        
        $.post(url, {
            start: _startDate,
            end: _endDate,
        }, function(rst){

            selectedTxids = rst.data;
            selectedTxidMap = rst.map;

            reloadTxRows();
            updateSelectAllCheckbox();
            recalculateSalesTx();

            fetchSummary(function(){
                hideIndicator()
            });

        }, 'json'). error(function(){

            hideIndicator();
        });
    }

    /**
     * A function to reselect the suggested transaction by a given amount.
     *
     * @param      integer  amount  The amount
     */
    var recalculateForNtxDate = function(targetAmount)
    {
        if(!isNonTxMode) {
            console.error('You should not run this function.');
            return;
        }
        
        var balance = 0;
        var remittanceAmount = 0;

        var broughtForward = getParticularData('broughtForward', '<?php echo $merchantCurrency?>')
        if(broughtForward && broughtForward.converted_amount){
            balance += parseFloat(broughtForward.converted_amount)
        }

        var batchRemittance = getParticularData('batchRemittance', '<?php echo $defaultCurrency?>')
        if(batchRemittance && batchRemittance.converted_amount){
            remittanceAmount += parseFloat(batchRemittance.converted_amount)
        }

        var batchRemittanceAdj = getParticularData('batchRemittanceAdj', '<?php echo $defaultCurrency?>')
        if(batchRemittanceAdj && batchRemittanceAdj.converted_amount){
            remittanceAmount += parseFloat(batchRemittanceAdj.converted_amount)
        }

        var instantRemittance = getParticularData('instantRemittance', '<?php echo $defaultCurrency?>')
        if(instantRemittance && instantRemittance.converted_amount){
            remittanceAmount += parseFloat(instantRemittance.converted_amount)
        }

        var instantRemittanceAdj = getParticularData('instantRemittanceAdj', '<?php echo $defaultCurrency?>')
        if(instantRemittanceAdj && instantRemittanceAdj.converted_amount){
            remittanceAmount += parseFloat(instantRemittanceAdj.converted_amount)
        }

        // For carryForward, reset to 0 for each reselect action. 
        // Amount should be re-entered if any remain balance after tx reselection
        var carryForward = getParticularData('carryForward', '<?php echo $merchantCurrency?>')
        if(carryForward && carryForward.converted_amount){
            carryForward.converted_amount = 0;
            // balance += parseFloat(carryForward.converted_amount)
        }

        var settlementHandlingFee = getParticularData('settlementHandlingFee', '<?php echo $merchantCurrency?>')
        if(settlementHandlingFee && settlementHandlingFee.converted_amount){
            balance += parseFloat(settlementHandlingFee.converted_amount)
        }

        var adhocAdj = getParticularData('adhocAdj', '<?php echo $merchantCurrency?>')
        if(adhocAdj && adhocAdj.converted_amount){
            balance += parseFloat(adhocAdj.converted_amount)
        }
        
        console.log('Remain Balance: ',balance + remittanceAmount, 'Target Settle Amount:', targetAmount)


        // For the balance does not meet the target
        if(balance + remittanceAmount >= targetAmount){

            carryForward.converted_amount = (balance  + remittanceAmount - targetAmount) * -1;

            recalculateSummary();

        }else{
            isNonTxMode = false;
            isSelectedAll = true;
            startDate = origStartDate;
            endDate = origEndDate;
            
            reselectTxByAmount(targetAmount)
            // fetchSummary(function(){
            // })

        }
    }

    /**
     * Group values and store in a DataSource instance
     *
     * @param      {DataSource}  ds          The instance of DataSource object for storing grouped values.
     * @param      {String}  fieldName   The field name from source data
     * @param      {Array}  sourceData  Array of source data.
     */
    var refreshDsForFields = function(ds, fieldName, sourceData)
    {
        var ary = []; var added = [];
        for(var idx = 0; idx < sourceData.length; idx ++){
            var dataItem = sourceData[idx];
            if(!dataItem[fieldName]) continue;
            var found = $.inArray(dataItem[fieldName], added);

            if(found < 0){
                added.push( dataItem[fieldName])
            }
        }

        for(var idx = 0; idx < added.length; idx ++ ){
            var r = {};
            r[ fieldName ] = added[idx];
            ary.push(r)
        }
        ds.data(ary);
    }

    var createBatchPostData = function(extraData){
        var postData = {
            start: startDate,
            end: endDate,
            // checksum: loadedChecksum,
            // start_ntx: isNonTxMode? startDateNtx : startDate,
            // end_ntx: isNonTxMode? endDateNtx : endDate,
            ntx: isNonTxMode && selectedTxids.length == 0 ? "yes":"no"
        };
        if(loadedChecksum) {
            postData.checksum = loadedChecksum;
            postData.checksumChanged = loadedChecksumChanged ? 'yes':'no';
        }
        if(extraData) {
            for(var key in extraData){
                postData[key ] = extraData[key];
            }
        }
        return postData;
    }

    var queryTx = function(callback)
    {
        isTxQueried = true;

        showIndicator();
        $self.find('button').prop('disabled', true)
        
        txDs.fetch().then(function(){

            $self.find('button').prop('disabled', false)
            hideIndicator();

            if(callback) callback( null);
        });
        
    }
    var currencies = [defaultCurrency, merchantCurrency];
    var recalculateSalesTx = function()
    {
        var data = txAllDs.data();
        $.each(currencies, function(idx, _currency){
            var amount = 0;
            var converted_amount = 0;
            var particular = getParticularData('sales', _currency);

            if(!particular) {
                console.log('recalculateSalesTx['+_currency+'] NOT FOUND');
                return;
            }
            $.each(data, function(idx2, row){
                
                if( row.tx_currency == _currency){

                    // if the tx is selected,
                    if(typeof selectedTxidMap[ row.id ] != 'undefined' && selectedTxidMap[ row.id ]){

                        if(row.tx_net_amount === null) {
                            console.error('Transaction net amount is null for txid', row.id, row)
                            return;
                        }
                        if(row.converted_amount === null) {
                            console.error('Transaction converted net amount is null for txid', row.id, row)
                            return;
                        }
                        if(row.tx_net_amount === 0) {
                            console.info('Transaction net amount is zero for txid', row.id)
                            return;
                        }
                        if(row.converted_amount === 0) {
                            console.info('Transaction converted net amount is zero for txid', row.id)
                            return;
                        }
                        // console.log(row.id, row.tx_net_amount, row.converted_net_amount)
                        row.tx_net_amount = parseFloat(row.tx_net_amount);
                        row.converted_amount = parseFloat(row.converted_amount);

                        if (row.tx_net_amount != 0 && isNaN(row.tx_net_amount)) {
                            console.error('Transaction net amount is not a valid number for txid', row.id)
                            return;
                        }
                        if (row.converted_amount != 0 && isNaN(row.converted_amount)) {
                            console.error('Transaction converted net amount is not a valid number for txid', row.id)
                            return;
                        }
                        amount += row.tx_net_amount;
                        converted_amount += row.converted_amount;
                    }
                }
            
            })
            if(particular){
                console.log('recalculateSalesTx['+_currency+']:', amount, converted_amount);
                particular.amount = amount;
                particular.converted_amount = converted_amount;
            }
        })
    }

    var recalculateSummaryParticulars = ['broughtForward','sales','refund','batchRemittance','batchRemittanceAdj','instantRemittance','instantRemittanceAdj','carryForward','adhocAdj'];

    /**
     * A local function to recalculate all the details and update particular grid.
     */
    var recalculateSummary = function(){
        
        var totalSettlementAmount = getParticularData('totalSettlementAmount', merchantCurrency);
        totalSettlementAmount.converted_amount = 0;

        console.log('>>> recalculateSummary');

        $.each(currencies, function(idx, _currency){
            var amount = 0;
            var converted_amount = 0;

            // List out all particulars
            $.each(recalculateSummaryParticulars, function(idx2, particularName){
                var particular = getParticularData(particularName, _currency);
                if(!particular){
                    // console.log('Cannot find particular: '+particularName +'-'+_currency )
                    return;
                }
                if(typeof particular.amount != 'undefined') {
                    particular.amount = parseFloat(particular.amount);
                    amount += (particular.amount);
                }
                if(typeof particular.converted_amount != 'undefined'){
                    particular.converted_amount = parseFloat(particular.converted_amount);
                    converted_amount += (particular.converted_amount);
                }
            })

            var settlementAmount = getParticularData('settlementAmount', _currency);
            if(settlementAmount){
                settlementAmount.amount = (amount);
                settlementAmount.converted_amount = ( converted_amount);
            }

            var settlementHandlingFee = getParticularData('settlementHandlingFee', _currency);
            if(settlementHandlingFee && totalSettlementAmount.converted_amount) {
                totalSettlementAmount.converted_amount += settlementHandlingFee.converted_amount;
            }
            
            totalSettlementAmount.converted_amount += (converted_amount);

        })
        updateButton();

        // Update the grid directly
        particularsDs.data( particulars );
    }

    // Select all transactions
    var selectAllTransaction = function(shouldSelectedAll, callback  ){

        
        console.log('>>> selectAllTransaction:', shouldSelectedAll);

        var data = txAllDs.data();
        

        if(typeof shouldSelectedAll == 'undefined') shouldSelectedAll = true;
        isSelectedAll = shouldSelectedAll;

        selectedTxidMap ={};
        if(shouldSelectedAll){
            for(var i = 0; i < data.length; i ++){
                var row = data[i];
                selectedTxidMap[ row.id ] = true;
            }
        }

        // console.log('selectAllTransaction', data);
        console.log('selectedMap:', selectedTxidMap);

        reloadTxRows();
        reloadTxList();
        updateSelectAllCheckbox();
        recalculateSalesTx();
        recalculateSummary();
        if(callback) callback();
        // fetchSummary(function(){
        //     recalculateSummary();
        //     if(callback) callback();
        // })
    }

    var downloadBatch = function(){
        var url = "<?=$this->Url->build(['action'=>'queueDownloadPreview', $masterMerchant['id'] ])?>";
        var postData = createBatchPostData();

        postData.txid = selectedTxids.join(',');
        postData.report_date = $('#tbReportDate').val();
        
        // Tell server that hows much of the broughtForward.
        var broughtForward = getParticularData('broughtForward', merchantCurrency);
        if(broughtForward ){
            postData['particulars[broughtForward-'+merchantCurrency+']'] = {
                converted_amount: broughtForward.converted_amount,
                remarks: broughtForward.remarks,
            }
        }
        // Tell server that hows much of the carryforword.
        var carryForward = getParticularData('carryForward', merchantCurrency);
        if(carryForward ){
            postData['particulars[carryForward-'+merchantCurrency+']'] = {
                converted_amount: carryForward.converted_amount,
                remarks: carryForward.remarks,
            }
        }
        // Tell server that hows much of the adhoc adj.
        var adhocAdj = getParticularData('adhocAdj', merchantCurrency);
        if(adhocAdj ){
            postData['particulars[adhocAdj-'+merchantCurrency+']'] = {
                converted_amount: adhocAdj.converted_amount,
                remarks: adhocAdj.remarks,
            }
        }
        // Tell server that hows much of the footnotes
        var footnotes = getParticularData('footnotes');
        if(footnotes ){
            postData['particulars[footnotes]'] = {
                remarks: footnotes.remarks,
            }
        }

        
        startQueueJob(url, postData, 5000 );
    }

    var submitBatch = function(){
        var url = "<?=$this->Url->build(['action'=>'submit', $masterMerchant['id'] ])?>";
        var postData = createBatchPostData();

        postData.txid = selectedTxids.join(',');
        postData.report_date = $('#tbReportDate').val();
        
        // Tell server that hows much of the broughtForward.
        var broughtForward = getParticularData('broughtForward', merchantCurrency);
        if(broughtForward ){
            postData['particulars[broughtForward-'+merchantCurrency+']'] = {
                converted_amount: broughtForward.converted_amount,
                remarks: broughtForward.remarks,
            }
        }
        // Tell server that hows much of the carryforword.
        var carryForward = getParticularData('carryForward', merchantCurrency);
        if(carryForward ){
            postData['particulars[carryForward-'+merchantCurrency+']'] = {
                converted_amount: carryForward.converted_amount,
                remarks: carryForward.remarks,
            }
        }
        // Tell server that hows much of the adhoc adj.
        var adhocAdj = getParticularData('adhocAdj', merchantCurrency);
        if(adhocAdj ){
            postData['particulars[adhocAdj-'+merchantCurrency+']'] = {
                converted_amount: adhocAdj.converted_amount,
                remarks: adhocAdj.remarks,
            }
        }
        // Tell server that hows much of the footnotes
        var footnotes = getParticularData('footnotes');
        if(footnotes ){
            postData['particulars[footnotes]'] = {
                remarks: footnotes.remarks,
            }
        }
        // Tell server that hows much of the totalSettlementAmount in client side
        var totalSettlementAmount = getParticularData('totalSettlementAmount', merchantCurrency);
        if(totalSettlementAmount ){
            postData['particulars[totalSettlementAmount]'] = {
                converted_amount: totalSettlementAmount.converted_amount,
            }
        }

        showIndicator();
        $.post(url, postData, function(rst){
            if(rst.status && rst.status == 'done'){
                if(rst.url)
                    location.href = rst.url;
                else{
                    alert('<?=__('Done')?>');
                    location.href = '<?=$this->Url->build(['action'=>'index'])?>';
                }
                return ;
            }
            hideIndicator();

            if(rst.status == 'error'){
                if(rst.type == 'UnmatchedTx' || rst.type == 'UnmatchedSettlementStatus' || rst.type == 'InvalidTotalSettlementAmount'){
                    alert('Settlement state has changed while you are editing, Please try again.');
                }else if(rst.type == 'CannotCreateToken'){
                    alert('System busy, please try again in a few minutes');
                    // location.href = '<?=$this->Url->build(['action'=>'index' ])?>';
                    return;
                }else if(rst.type == 'TotalSettlementAmountLowerThanZero'){
                    alert('Total settlement amount is lower than zero.');
                }else if(rst.msg){
                    alert(rst.msg);
                }
            }
            console.log(rst);
        },'json').error( function(){
            hideIndicator();
            alert('Connection error. Please try again later.');
        })
    }
    
    var reloadTxRows = function(){
        // Trigger 'update' event on each row, 'checkbox' flag will be changed
        grid.table.find('[role=row]').each(function(){
            $(this).trigger('update');
        })
    }

    var resetCarryForward = function(){
        
        var carryForward = getParticularData('carryForward', '<?php echo $merchantCurrency?>')
        if(carryForward && carryForward.converted_amount){
            carryForward.converted_amount = 0;
            carryForward.remarks = '';
            // balance += parseFloat(carryForward.converted_amount)
        }
        // recalculateSummary();

    }

    var afterNtxDateChange = function(){
        
        updateNtxDate();
        resetCarryForward();

        resetTxTargetAmount();

        isNtxDateChanged = true;
    }
    var updateNtxDate = function(){
        // console.log(endDateNtxDp.value(), startDateNtxDp.value())
        endDateNtx = kendo.toString(endDateNtxDp.value(), "yyyy-MM-dd");
        startDateNtx = kendo.toString(startDateNtxDp.value(), "yyyy-MM-dd");
        endDate = kendo.toString(endDateNtxDp.value(), "yyyy-MM-dd");
        startDate = kendo.toString(startDateNtxDp.value(), "yyyy-MM-dd");


        startDateNtxDp.max( endDateNtxDp.value() )
        endDateNtxDp.min( startDateNtxDp.value() )
    }

    var reloadSummary = function(callback)
    {
        showIndicator();
        fetchSummary( function(error){
            // Initial data load and select all transaction by default.
            if(error) {
                hideIndicator();
                return;
            }
    
            // Promise form.
            txAllDs.fetch().then(function(){
                var allData = txAllDs.data(); 
    
                isTxQueried = true;
                totalTx = allData.length;
    
                refreshDsForFields(txDateDs, 'reconciled_state_time', allData);
                
                hideIndicator();
                if(callback) callback();
            });
            
        }, true);
    }

    var $txGrid = $('#grid-transaction');
    $txGrid.kendoGrid({
        columns: txColumns,
        dataSource: txDs,
        persistSelection: true,
        // selectable: 'multiple, row',
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        autoBind: false,
        height:600,
        filterable: {
            extra: false
        },
        change: function (e, args) {
            var grid = e.sender;
            var items = grid.items();

            items.each(function (idx, row) {
                var dataItem = grid.dataItem(row)
                if(! dataItem.id ){
                    return;
                }

                var found = typeof selectedTxidMap[ dataItem.id ] != 'undefined';
                if( found ){
                    grid.tbody.find("tr[data-uid='" + dataItem.uid + "']")
                    .addClass("k-state-selected")
                    .find(".checkbox")
                    .attr("checked","checked");
                }
            });

            updateSelectAllCheckbox();
        },
        dataBound: function onDataBound(e) {
            var view = this.dataSource.view();

            // Select all selected items during paging data loaded/binded to rows
            for(var idx = 0; idx < view.length; idx ++){
                var dataItem = view[idx];

                // console.log(dataItem , dataItem.uid , typeof selectedTxidMap[ dataItem.id ] != 'undefined')
                var found = typeof selectedTxidMap[ dataItem.id ] != 'undefined';
                if( found ){
                    grid.tbody.find("tr[data-uid='" + dataItem.uid + "']")
                    .addClass("k-state-selected")
                    .find(".checkbox")
                    .attr("checked","checked");
                }
            }
            updateSelectAllCheckbox();
        },
        pageable: {
            refresh: true,
            //pageSizes: true,
            pageSizes: [10, 25, 50, 100],
        },
        noRecords: true,
        messages: {
            noRecords: "<?=__('No record found.')?>"
        },
        
        resizable: true,
        //scrollable: false,
    });

    // Update the array from the map and the flag of isNonTxMode & isSelectedAll
    var reloadTxList = function(){
        selectedTxids.length = 0;

        if(selectedTxidMap){
            for(var txid in selectedTxidMap){
                if(selectedTxidMap[txid])
                    selectedTxids.push(txid);
            }
        }
        isNonTxMode = selectedTxids.length < 1;
        isSelectedAll = selectedTxids.length >= totalTx;

        console.log('ReloadTxList, totalSelected:',selectedTxids.length,'totalTx:',totalTx,'isNonTxMode:',isNonTxMode,'isSelectedAll:',isSelectedAll);
    }

    var grid = $txGrid.data("kendoGrid");
    grid.thead
    .on("change", ".checkbox" , function selectAllRows() {
        var checked = this.checked;

        var view = txDs.view();

        for(var idx = 0; idx < view.length; idx ++){

            var dataItem = view[idx];

            var found = typeof selectedTxidMap[ dataItem.id ] != 'undefined';

            if( checked ){
                // If checked but not found in the list
                if(!found)
                    selectedTxidMap[dataItem.id] = true;
            }else{
                // If unchecked but found in the list
                if( found)
                    delete selectedTxidMap[dataItem.id];
            }
        }

        // We will not use the ntx date picker selected values for selection when reloading.
        isNtxDateChanged = false;

        resetTxTargetAmount();
        resetCarryForward();
        
        showIndicator();

        // asynchronous call
        setTimeout(function(){

            reloadTxList();
            reloadTxRows();
            updateSelectAllCheckbox();
            recalculateSalesTx();
            recalculateSummary();
            updateFooterMessage();
            reloadDateRange();
            updateButton();
            hideIndicator();
        },1);
    })
    grid.table
    .on("change", ".checkbox" , function selectRow() {
        var checked = this.checked,
            $row = $(this).closest("tr");

        var dataItem = grid.dataItem($row);

        var found = typeof selectedTxidMap[ dataItem.id ] != 'undefined';

        if( checked ){
            // If checked but not found in the list
            if(!found)
                selectedTxidMap[dataItem.id] = true;   
        }else{
            // If unchecked but found in the list
            if( found)
                delete selectedTxidMap[dataItem.id];
        }

        // We will not use the ntx date picker selected values for selection when reloading.
        isNtxDateChanged = false;

        $row.trigger('update');

        resetTxTargetAmount();
        resetCarryForward();

        showIndicator();

        // asynchronous call
        setTimeout(function(){
            reloadTxList();
            updateSelectAllCheckbox();
            recalculateSalesTx();
            recalculateSummary();
            updateFooterMessage();
            reloadDateRange();
            updateButton();
            hideIndicator();

            // Update the header select all checkbox
            // showIndicator();
            // fetchSummary(function(){
            //     recalculateSummary();
            //     updateFooterMessage();
            //     hideIndicator();
            // })
        },1);


    })
    .on('update', '[role=row]', function updateRow(){
        var $row = $(this);
        var dataItem = grid.dataItem($row);

        var checked = typeof selectedTxidMap[ dataItem.id ] != 'undefined';

        if (checked) {
            //-select the row
            $row.addClass("k-state-selected");
            if(!$row.find('.checkbox').prop('checked'))
                $row.find('.checkbox').prop('checked', true)
        } else {
            //-remove selection
            $row.removeClass("k-state-selected");
            if($row.find('.checkbox').prop('checked'))
                $row.find('.checkbox').prop('checked', !true)
        }
    })

    //////////
    var gridParticulars, $gridParticulars = $('#grid-particulars');
    $gridParticulars.kendoGrid({
        columns: particularsColumns,
        dataSource: particularsDs,
        persistSelection: true,
        selectable: 'none',
        sortable: false,
        noRecords: true,
        filterable:false,
        detailInit: function(e) {
            if(e.data.type != 'subgrid') return;

            if(e.data.particular == 'sales'){
                showPaymentTransaction(e, 'sales', e.data.currency, function(){
                    
                    return {txid: ''+selectedTxids.join(',')}
                });
            }

            // Select all for refund transaction
            if(e.data.particular == 'refund'){
                showPaymentTransaction(e, 'refund', e.data.currency);
            }
            // e.detailRow.find(".grid").kendoGrid({
            //     dataSource: e.data.products
            // });
            if(e.data.particular == 'batchRemittance'){
                showBatchRemittance(e, e.data.particular, e.data.currency)
            }
            if(e.data.particular == 'batchRemittanceTx'){
                showBatchRemittanceDetail(e, e.data.particular, e.data.currency)
            }
            if(e.data.particular == 'instantRemittance'){
                showInstantRemittance(e, e.data.particular, e.data.currency)
            }
            if(e.data.particular == 'batchRemittanceAdj' || e.data.particular == 'instantRemittanceAdj'){
                showBatchRemittanceAdjustment(e, e.data.particular, e.data.currency)
            }
        },
        // dataBound: function() {
        //     this.expandRow(this.tbody.find("tr.k-master-row").first());
        // },
        dataBound: function onDataBound(e) {
            var grid = this;
            var view = this.dataSource.view();

            // Select all selected items during paging data loaded/binded to rows
            for(var idx = 0; idx < view.length; idx ++){
                var dataItem = view[idx];

                var $tr = grid.tbody.find("tr[data-uid='" + dataItem.uid + "']");
                $tr.find('.k-hierarchy-cell > .k-i-expand').hide(); // hack: hiding the left button to keep expand-row functions

            }
        },
        messages: {
            noRecords: "<?=__('No record found.')?>"
        },
        pageable: false,
        resizable: true,
        //scrollable: false,
    });
    gridParticulars = $('#grid-particulars').data('kendoGrid')

    // Control the subgrid
    $gridParticulars
    .on('click', '.btn-view-subgrid', function(){
        var data = $(this).data(),
            $row = $(this).closest("tr");

        var dataItem = gridParticulars.dataItem($row);
        
        $row.toggleClass('expanded')
        if($row.hasClass('expanded')) {
            gridParticulars.expandRow($row)
        } else {
            gridParticulars.collapseRow($row)
        };
    })
    .on('click', '.btn-edit-remark', function(){
        var data = $(this).data();
        var particularsIndex = particularsIndexing[ data.id ];
        var particularsPart = particulars[ particularsIndex ];

        var templateName = null;
        var fields = [];    

        if(particularsPart.type == 'amount_remark_change' ){
            templateName = 'amount_remark_change'
            fields = ['converted_amount','remarks'];
        }

        if(particularsPart.type == 'remark_change' ){
            templateName = 'remark_change'
            fields = ['remarks'];
        }

        if(particularsPart.type == 'remark_long_change' ){
            templateName = 'remark_long_change'
            fields = ['remarks'];
        }

        var newData = {id: data.id};

        if(templateName){
            var $win = $("[data-template="+templateName+"].template").clone().removeClass('.template');
            var windowInstance = $win.kendoWindow({
                title: "<?=__('Editing')?>",
                visible: false,
                modal: true,
                actions: [
                    // "Close"
                ],
                close: function(){
                    windowInstance.destroy()
                }
            }).data("kendoWindow");
            windowInstance.center().open();


            for(var i = 0 ; i < fields.length; i ++){
                var fieldName = fields[i];
                var val = typeof particularsPart [fieldName] != 'undefined' ? particularsPart [fieldName] : '';
                newData[ fieldName ] = val;
                $win.find('[data-field='+fieldName+']').val( val)
            }
            $win
            .on('change', '[data-field]', function(){
                var fieldName = $(this).data('field');
                var fieldType = $(this).prop('type');
                var val = $(this).val();
                if(fieldType == 'number'){
                    if(!val || val.length < 1) val = '0';
                }
                newData [ fieldName ] = val;
            })
            .on('click','.btn-cancel', function(evt){
                evt.preventDefault();
                windowInstance.close();
            })
            .on('click','.btn-confirm', function(evt){
                evt.preventDefault();
                windowInstance.close();
                
                // Update grid
                particularsDs.pushUpdate(newData)

                recalculateSummary();
                updateFooterMessage();
            })
        }
    })
    
    var resetTxTargetAmount = function(newValue){
        // Ask server to ignore the current checksum
        // loadedChecksum = null;
        loadedChecksumChanged = true;
        if(!newValue ) {
            $('.tf-target-amount').val('');
            return;
        }
        newValue = parseFloat(newValue)
        $('.tf-target-amount').val(newValue);
    }

    var getTargetAmount = function(){
        var targetAmount = $('.tf-target-amount').val();

        if(!targetAmount || !targetAmount.length) return;
        targetAmount = parseFloat(targetAmount)

        return targetAmount;
    }

    $('body')
    .on('click', '.btn-reselect-by-amount', function(){
        var targetAmount = getTargetAmount();

        if(targetAmount > 0){

            endDateNtxDp.value(yesturday)
            startDateNtxDp.value(earliesRange)  

            // Update date max/min range.
            updateNtxDate();

            reloadDateRange();
            
            reselectTxByAmount(targetAmount);
        }else{
            alert('Sorry, specified amount should be larger then 0.');
        }

    })
    .on('click', '.btn-reselect-by-date', function(){
        reselectTxByDate(startDateNtx, endDateNtx);
    })
    .on('click', '.btn-recal-summary', function(){
        showIndicator();
        fetchSummary( function(error){
            // Initial data load and select all transaction by default.
            if(error) {
                return;
            }

            hideIndicator();
    
        })
    })
    .on('click', '.btn-reload-summary', function(){
        if(!confirm('The modifications in the current session will be lost.'))return;

        reloadSummary(function(){
            txDs.page(1)
        }, true);
    })
    .on('click', '.btn-download-batch', function(){
        downloadBatch();
    })
    .on('click', '.btn-submit-batch', function(){
        if(!confirm('The Settlement Amount will be deducted from the merchant balance as Settlement.'))return;
        submitBatch();
    })

    var today = kendo.date.today();
    var yesturday = kendo.date.addDays(today, -1)
    var earliesRange = kendo.date.addDays(today, -15)
    var datePickerConfig = {
        format: "yyyy-MM-dd",
    };

    var startDateNtxDp  = $('#tbStartDateNtx').kendoDatePicker(datePickerConfig).data("kendoDatePicker");
    var endDateNtxDp = $('#tbEndDateNtx').kendoDatePicker(datePickerConfig).data("kendoDatePicker");
    var reportDateDp = $("[name=report_date]").kendoDatePicker(datePickerConfig).data("kendoDatePicker");

    reportDateDp.value(today)
    reportDateDp.min(today)
    reportDateDp.bind('change', updateFooterMessage);
    
    endDateNtxDp.value(new Date(endDate))
    startDateNtxDp.value(new Date(startDate))

    endDateNtxDp.max(yesturday)
    startDateNtxDp.max(yesturday)

    endDateNtxDp.bind('change',afterNtxDateChange);
    startDateNtxDp.bind('change', afterNtxDateChange);
    
    endDateNtx = kendo.toString(endDateNtxDp.value(), "yyyy-MM-dd");
    startDateNtx = kendo.toString(startDateNtxDp.value(), "yyyy-MM-dd");


    reloadSummary(function(){

        showIndicator();
        selectAllTransaction(true, function(){

            hideIndicator();
            recalculateSummary();
            updateFooterMessage();
            txDs.fetch()

        });
    });
    

})
</script>
<style>
@import url(<?=$this->Url->css('wc-extra')?>);

.k-master-row > td .hidden-unexpanded,
.k-master-row.expanded > td  .hidden-expanded{display:none;}
.k-master-row > td  .hidden-expanded,
.k-master-row.expanded > td  .hidden-unexpanded{display:inherit;}

.k-grid tr td{text-align:left; vertical-align:text-top;}

.content-block{margin: 5px 0;}
fieldset.thin{    margin: 0;
    padding-top: 10px;
    padding-bottom: 0;
}

.inline {margin-top:6px; margin-right:8px;}
.inline .text{float: left; }
.inline label {
    float: left;
    font-size: inherit;
    color: inherit;
    margin-left: 10px;
    margin-right: 10px;
}

.wc-particulars .k-edit-form-container{width:500px;}
</style>
<div class="hidden">
    <div data-template="amount_remark_change" class="k-popup-edit-form wc-particulars k-window-content k-content template">
        <div class="k-edit-form-container"> 
            <div class="k-edit-label">
                <label for="converted_amount"><?=__('Converted Amount')?></label>
            </div>
            <div class="k-edit-field">
                <input type="number" name="converted_amount" data-field="converted_amount" value="0" style="width: 100%;" />
            </div>

            <div style="margin:10px 20px;">
            <label for="remarks"><?=__('Remarks')?></label>
            <textarea name="remarks" data-field="remarks" style="width: 95%; height:100px;"></textarea>
            </div>


            <div class="k-edit-buttons k-state-default">
                <button type="button" class="k-primary k-button btn-confirm"><?=__('Confirm')?></button>
                <button type="button" class=" k-button btn-cancel"><?=__('Cancel')?></button>
            </div>
        </div>
    </div>
    <div data-template="remark_change" class="k-popup-edit-form wc-particulars k-window-content k-content template">
        <div class="k-edit-form-container"> 

            <div style="margin:10px 20px;">
            <label for="remarks"><?=__('Remarks')?></label>
            <textarea name="remarks" data-field="remarks" style="width: 95%; height:100px;"></textarea>
            </div>

            <div class="k-edit-buttons k-state-default">
                <button type="button" class="k-primary k-button btn-confirm"><?=__('Confirm')?></button>
                <button type="button" class="k-button btn-cancel"><?=__('Cancel')?></button>
            </div>
        </div>
    </div>
    
</div>