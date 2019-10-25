<div class="form search-input">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>

<?= $this->Form->create(null, ['url' => ['action' => 'processSubmit'] , 'name' => 'processSubmit']) ?>
    <fieldset>
        <legend><?= __('Batch Detail') ?></legend>
        <ul class="fieldlist">
            <li  class="row label-left">
                <div class="col-md-6 col-lg-4">
                    <label>Report Date:</label>
                    <div class="input"><span data-field="report_date"><?php echo $batchRow['report_date']->format('Y/m/d')?></span></div>
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
                    <div class="input"><span data-field="batch_status"><?php echo $batchRow['state']?></span></div>
                </div>
            </li>
        </ul>
    </fieldset>


    <div class="clearfix"></div>
    <div style="margin-top:0; margin-bottom:10px;">
        <di class="content-block">Summary:</div>

        <div class="content-block hidden">Loaded checksum: <span class="session-checksum"><?php echo $batchRow['editable_checksum']?></span></div>
        <div class="content-block hidden">Fetched checksum: <span class="fetched-checksum"></span></div>
        <div class="content-block">Transaction Date: <span class="lblStartDate"><?php echo date('Y-m-d', strtotime($startDate))?></span> to <span class="lblEndDate"><?php echo date('Y-m-d', strtotime($endDate))?></span></div>
        <div class="content-block">Settlement Currency: <?php echo $batchRow['settlement_currency']?></div>
        <div class="content-block">Settlement FX package: <?php echo $batchRow['fx_package']?></div>
<?php if($batchRow['fx_package'] == '2'){?>
        <div class="content-block">Settlement FX Rate: <?php echo number_format($batchRow['settlement_rate'],4)?></div>
<?php } ?>
    <div id="grid-particulars"></div>
    <div class="clearfix"></div>
        <div class="content-block"><span class="lblFooterMessage"></span></div>
    <div class="clearfix"></div>
    <div class="btn-wrap">
<?php if($batchRow['state'] == 'OPEN'):?>
<?= $this->Form->button(__('Re-calc'), ['type' => 'button', 'class'=>'left btn-recal-summary k-button']); ?>
<?= $this->Form->button(__('Reload'), ['type' => 'button', 'class'=>'left btn-reload-summary k-button']); ?>
<?= $this->Form->button(__('Preview'), ['type' => 'button', 'class'=>'left btn-download-batch k-button']); ?>
<?= $this->Form->button(__('Complete'), ['type' => 'button', 'class'=>'left btn-submit-batch k-button']); ?>
<?php else: ?>
<?= $this->Form->button(__('Download'), ['type' => 'button', 'class'=>'left btn-download-batch k-button']); ?>
<?php endif;?>
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
    var batchId = '<?php echo $batchId?>';
    var batchState = '<?php echo $batchRow['state']?>';
    var startDate = '<?php echo $startDate?>';
    var endDate = '<?php echo $endDate?>';
    var editableChecksum = '<?php echo $batchRow['editable_checksum']?>';
    var selectedTxids = [];

    var newSelectedTxIds = {};

    function showDetailsGrid(e, particular, currency, detailPostData, model, columns, detailInit, detailExpand, detailCollapse, dataBound, preferredHeight) {

        if(!preferredHeight) preferredHeight = 420;
        var cfg = {
            dataSource: {
                type: "json",
                transport: {
                    read: {
                        url: "<?=$this->Url->build(['action'=>'fetchBatchDetail',$batchId ]).'?'?>",
                        type: "post",
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
                    data: 'data',
                    total: 'total',
                    model: model
                },
            },
            height: preferredHeight,
            detailInit: detailInit,
            dataBound: dataBound,
            detailExpand: detailExpand,
            detailCollapse: detailCollapse,
            noRecords: true,
            messages: {
                noRecords: ('No record found.')
            },
            scrollable: true,
            sortable: true,
            
            pageable: {
                refresh: true,
                //pageSizes: true,
                pageSizes: [10, 25, 50, 100],
            },
            columns: columns,
        };

        $("<div/>").appendTo(e.detailCell).kendoGrid(cfg);
    }
    function showSalesPaymentTransaction(e, particular, currency, postData) {
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
            { field: "convert_rate", title: "FX Rate", width: 120, format: "{0:n4}", sortable: false, attributes:{style:'text-align:right'} },
            { field: "tx_convert_amount", title: "Converted Amount", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "merchant_ref", title: "Merchant Ref", width: 320, sortable:false },
            { field: "transaction_id", title: "Transaction ID", width: 320, sortable: false },

<?php if($batchRow['state'] == 'OPEN'):?>
            {title: 'Actions', sortable: false, filterable: false, width: 120, template: function(entity){
                return $('<a href="javascript:void(0);" class="btn-tx-hold"><span>Hold</span></a>')
                    .attr('data-id', entity.id).get(0).outerHTML
            } },
<?php endif;?>
            {}
        ])
        
    }
    function showPaymentTransaction(e, particular, currency, postData) {
        showDetailsGrid(e, particular, currency, postData,{
            id: "id",
            fields: {
                "converted_amount": {
                    type:"number"
                },
                "converted_fee": {
                    type:"number"
                },
                "tx_convert_amount": {
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
            { field: "convert_rate", title: "FX Rate", width: 120, format: "{0:n4}", sortable: false, attributes:{style:'text-align:right'} },
            { field: "tx_convert_amount", title: "Converted Amount", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "merchant_ref", title: "Merchant Ref", width: 320, sortable:false },
            { field: "transaction_id", title: "Transaction ID", width: 320, sortable: false },
            {}
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
            { field: "target_name", title: "Channel", width: 320,},
            { title: 'Actions', sortable: false, filterable: false, width: 240, attributes:{style:'min-width:100px'}, template: function(entity){
                
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
        },null, null, function(){
            
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
            { field: "paid_amount", title:"Paid Amount", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "convert_currency", title:"Currency", width: 120 },
            { field: "converted_amount", title:"Converted Amount", width: 120, format: "{0:n2}", attributes:{style:'text-align:right'}  },
            { field: "convert_rate", title:"Rate", width: 100, format: "{0:n4}"  },
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
            { field: "ir_time", title:"Date", width: 120 },
            { field: "id", title:"Trans ID", width: 120 },
            { field: "amount", title:"Adjustment CNY", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "convert_currency", title:"Currency", width: 120 },
            { field: "converted_amount", title: "Converted Amount", width: 180, format: "{0:n2}", attributes:{style:'text-align:right'} },
            { field: "convert_rate", title: "Rate", format: "{0:n4}", width:120, attributes:{style:'text-align:right'} },
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

    var createBatchPostData = function(extraData){
        var postData = {
            editableChecksum: editableChecksum,
        };

        if(extraData) {
            for(var key in extraData){
                postData[key ] = extraData[key];
            }
        }
        return postData;
    }

    var txDateDs = new kendo.data.DataSource({
        sortable: true,
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
    });

    var txColumns = [
        {field:'reconciled_state_time',title:'Date', width: 120, sortable: true, template: function(entry){
                return kendo.toString(new Date(entry.reconciled_state_time), 'yyyy-MM-dd')
            }, filterable: {
            dataSource: txDateDs,
            checkAll: true,
            multi: true
        } },
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
        {field:'convert_rate',title:'Convert Rate', width: 100, sortable: true, filterable: false, format: "{0:n4}", attributes:{style:'text-align:right'}},
        
        {field:'tx_convert_amount',title:'Converted Amount', width: 180, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:'text-align:right'}},
        {field:'merchant_ref',title:'Merchant Ref', width: 320, sortable: false, filterable: false},
        {field:'transaction_id',title:'Transaction Id', width: 320, sortable: false, filterable: false},
<?php if($batchRow['state'] == 'OPEN'):?>
        {title: 'Actions', sortable: false, filterable: false, width: 120,  template: function(entity){
            return $('<a href="javascript:void(0);" class="btn-tx-hold"><span>Hold</span></a>')
                .attr('data-id', entity.id).get(0).outerHTML
        } },
<?php endif;?>
        { }
    ];

    var fetchSummary = function(callback) {

        var url = "<?=$this->Url->build(['action'=>'fetchBatch', $batchId ])?>";
        var postData = createBatchPostData();

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

        $.post(url, postData, function(rst){

            if(rst.currentChecksum != editableChecksum){
                editableChecksum = rst.currentChecksum;
                // alert('The settlement batch has been changed while you are editing, please try again.');
            }

            if(rst.merchant) {
                if(rst.merchant.settleBankAccount) {
                    merchantBankAccount = rst.merchant.settleBankAccount;
                    merchantBankName = rst.merchant.settleBankName;
                }
            }

            // If the state changed already, refresh the page
            if(rst.state != '<?php echo $batchRow['state']?>'){
                reloadBatchPage();
                return;
            }
            
            for(var key in rst.particulars){
                var updated = rst.particulars[key];


                // If the particular is missing
                if(typeof particularsIndexing[ key ] == 'undefined')
                    continue;

                var offset = particularsIndexing[ key ] ;
                var particularsPart = particulars [ offset ];

                if(typeof particularsPart.amount != 'undefined')
                    particularsPart.amount = updated.amount;
                if(typeof particularsPart.converted_amount != 'undefined')
                    particularsPart.converted_amount = updated.converted_amount;

                if(typeof particularsPart.remarks != 'undefined')
                    particularsPart.remarks = updated.remarks;
            }

            updateButton();

            particularsDs.data( particulars );

            $('.fetched-checksum').text( rst.checksum )

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
                'reportDate': '<?php if(!empty($batchRow['report_date'])) echo $batchRow['report_date']?>',
            }));
        }
    }

    var updateButton = function(){

        var totalAmount = getParticularData('totalSettlementAmount', '<?php echo $merchantCurrency?>') .converted_amount;
        var specifiedAmount = $('.tf-target-amount').val();
        
        // Disable process button if the total amount is lower than zero 
        var isTotalLessThanZero = totalAmount < 0;

        
        var isTotalLessThanRequested = false;
        if(specifiedAmount && specifiedAmount.length > 0){
            if( parseFloat(""+totalAmount).toFixed(2) < parseFloat(specifiedAmount).toFixed(2) ) {
                isTotalLessThanRequested = true;
            }
        } 

        $('.btn-submit-batch').prop('disabled', isTotalLessThanZero || isTotalLessThanRequested);

    }
    var txAllDs = new kendo.data.DataSource({
        paging: false,
        transport: {
            read: {
                url:"<?=$this->Url->build(['action'=>'fetchInitialBatch', $batchId ])?>",
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


    var txDs = new kendo.data.DataSource({
        paging: true,
        pageSize: 25,
        sortable: true,

        transport: {
            read: {
                url:"<?=$this->Url->build(['action'=>'fetchBatchDetail',$batchId ])?>",
                data: function(config){
                    var postData = createBatchPostData(config);
                    postData.particular = 'sales'
                    return postData;
                },
                dataType: 'json',
                type:"post",
            }
        },
        requestEnd: function(e) {
            var response = e.response;
            var type = e.type;
            
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
        serverFiltering: true,
        serverPaging: true,
        serverSorting: true,
        sort: { field: "_index", dir: "asc" },

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
    });


    var particularsColumns = [
        {field:'particular_name',title:'Particular', width: 240, sortable: false, filterable: false},
        {field:'currency',title:'Currency', width: 80, sortable: false, filterable: false},
        {field:'amount',title:'Amount', width: 180, sortable: false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {field:'converted_amount',title:'Converted Amount', width: 180, sortable: false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {field:'remarks',title:'Remarks', width: 360, sortable: false, filterable: false, template: function(entity){
            if(!entity.remarks) return '';
            return (''+entity.remarks).replace(/[\n\r]+/g,"<br />\n");
        }},
        {title:'Actions', sortable: false, filterable: false,  width: 120, template: function(entity){
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


    var currencies = [defaultCurrency, merchantCurrency];
    var recalculateSalesTx = function(){
        var data = txDs.data();
        $.each(currencies, function(idx, _currency){
            var amount = 0;
            var converted_amount = 0;
            $.each(data, function(idx2, row){
                
                if( row.tx_currency == _currency){

                    // if the tx is selected,
                    if(typeof newSelectedTxIds[ row.id ] != 'undefined' && newSelectedTxIds[ row.id ]){
                        // console.log(row);
                        amount += parseFloat(row.tx_net_amount);
                        converted_amount += parseFloat(row.converted_net_amount);
                    }
                }
            
            })
            var particular = getParticularData('sales', _currency);
            if(particular){
                particular.amount = amount;
                particular.converted_amount = converted_amount;
            }
        })
    }

    var recalculateSummaryParticulars = ['broughtForward','sales','refund','batchRemittance','batchRemittanceAdj','instantRemittance','instantRemittanceAdj','carryForward','adhocAdj'];
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
                    // console.log(particularName+'['+_currency+'].amount', particular.amount)
                }
                if(typeof particular.converted_amount != 'undefined'){
                    particular.converted_amount = parseFloat(particular.converted_amount);
                    converted_amount += (particular.converted_amount);
                    // console.log(particularName+'['+_currency+'].converted_amount', particular.converted_amount)
                }
            })

            var settlementAmount = getParticularData('settlementAmount', _currency);
            if(settlementAmount){
                settlementAmount.amount = (amount);
                settlementAmount.converted_amount = ( converted_amount);
                
                // console.log('settlementAmount['+_currency+'].amount', settlementAmount.amount)
                // console.log('settlementAmount['+_currency+'].converted_amount', settlementAmount.converted_amount)
            }

            var settlementHandlingFee = getParticularData('settlementHandlingFee', _currency);
            if(settlementHandlingFee && totalSettlementAmount.converted_amount) {
                totalSettlementAmount.converted_amount += settlementHandlingFee.converted_amount;
            }
            
            totalSettlementAmount.converted_amount += (converted_amount);

        })


        // console.log('totalSettlementAmount['+merchantCurrency+'].converted_amount', totalSettlementAmount.converted_amount)
            updateButton();

        // Update the grid directly
        particularsDs.data( particulars );
    }

    // Select all transactions
    var selectAllTransaction = function(shouldSelected ){
        
    }

    var downloadBatch = function(){
        var url = "<?=$this->Url->build(['action'=>'queueDownloadBatch', $batchId ])?>";
        var postData = {};


        postData.reportDate = $('#tbReportDate').val();
        
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

    var holdTxFromBatch = function(txid) {
        var url = "<?=$this->Url->build(['action'=>'holdTx', $batchId ])?>";
        var postData = {};

        postData.txid = txid;
        postData.checksum = editableChecksum;
        
        // Tell server that hows much of the totalSettlementAmount in client side
        var totalSettlementAmount = getParticularData('totalSettlementAmount', merchantCurrency);
        if(totalSettlementAmount ){
            postData['particulars[totalSettlementAmount]'] = {
                converted_amount: totalSettlementAmount.converted_amount,
            }
        }
        
        showIndicator();
        $.post(url, postData, function(rst){
            hideIndicator();
            if(rst.status && rst.status == 'done'){

                // update client-side checksum value
                editableChecksum = rst.checksum;

                txDs.data([])
                txDs.fetch();
                // // Reload all data
                // fetchSummary( function(error){
                //     // Initial data load and select all transaction by default.
                //     if(error) {
                //         hideIndicator();
                //         alert('Cannot reload the summary grid.')
                //         console.log(error);
                //         return;
                //     }

                //     updateFooterMessage();
                //     txDs.fetch().then(function(){
                //         hideIndicator();
                //     })
                // });
                return;
            }

            if(rst.status == 'error'){
                if(rst.type == 'UnmatchedTx' || rst.type == 'UnmatchedChecksum' || rst.type == 'UnmatchedSettlementStatus' || rst.type == 'InvalidTotalSettlementAmount'){
                    alert('Settlement state has changed while you are editing, Please try again.');
                }else if(rst.type == 'CannotCreateToken'){
                    alert('System busy, please try again in a few minutes');
                    location.href = '<?=$this->Url->build(['action'=>'search' ])?>';
                    return;
                }else if(rst.type == 'TotalSettlementAmountLowerThanZero'){
                    alert('Total settlement amount is equal to / lower than zero.');
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

    var updateChecksum = function(newVal)
    {
        editableChecksum = newVal;
        $('.session-checksum').text(newVal);
    }

    var submitParticularChange = function(particularData, callback)
    {
        var url = "<?=$this->Url->build(['action'=>'particularChange', $batchId ])?>";
        var postData = {};
        postData.checksum = editableChecksum;
        
        // Tell server that hows much of the totalSettlementAmount in client side
        var totalSettlementAmount = getParticularData('totalSettlementAmount', merchantCurrency);
        if(totalSettlementAmount ){
            postData['particulars[totalSettlementAmount]'] = {
                converted_amount: totalSettlementAmount.converted_amount,
            }
        }
        postData['column[particular]'] = particularData.particular;
        if(particularData.currency)
            postData['column[currency]'] = particularData.currency;
        
        // Tell server that hows much of the broughtForward.
        if(particularData.type == 'amount_remark_change'){

            postData['particulars['+particularData.id+']'] = {
                converted_amount: particularData.converted_amount,
                remarks: particularData.remarks,
            }
        }else if(particularData.type == 'remark_change' || particularsPart.type == 'remark_long_change'){
            postData['particulars['+particularData.id+']'] = {
                remarks: particularData.remarks,
            }
        }
        
        showIndicator();
        $.post(url, postData, function(rst){

            if(rst.status && rst.status == 'done'){

                if(rst.checksum) {
                    updateChecksum(rst.checksum);
                }
                
                fetchSummary(function(){
                    updateFooterMessage();
                    hideIndicator();
                    if(callback) callback(null);
                });
                return;
            }

            if(rst.status == 'error'){
                if(rst.type == 'UnmatchedTx' || rst.type == 'UnmatchedChecksum' || rst.type == 'UnmatchedSettlementStatus' || rst.type == 'InvalidTotalSettlementAmount'){
                    alert('Settlement state has changed while you are editing, Please try again.');
                }else if(rst.type == 'CannotCreateToken'){
                    alert('System busy, please try again in a few minutes');
                    location.href = '<?=$this->Url->build(['action'=>'index' ])?>';
                    return;
                }else if(rst.type == 'TotalSettlementAmountLowerThanZero'){
                    alert('Total settlement amount is equal to / lower than zero.');
                }else if(rst.msg){
                    alert(rst.msg);
                }
            }
            if(callback) callback(rst);
            console.log(rst);
        },'json').error( function(){
            hideIndicator();
            alert('Connection error. Please try again later.');
        })
    }

    var reloadBatchPage = function()
    {
        
        location.href = '<?=$this->Url->build(['action'=>'view', $batchId ])?>?t='+(new Date().getTime());
    }

    var submitBatch = function()
    {
        var url = "<?=$this->Url->build(['action'=>'complete', $batchId ])?>";
        var postData = {};
        postData.checksum = editableChecksum;
        
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
                reloadBatchPage();
                return ;
            }

            hideIndicator();
            if(rst.status == 'error'){
                if(rst.type == 'UnmatchedTx' || rst.type == 'UnmatchedChecksum' || rst.type == 'UnmatchedSettlementStatus' || rst.type == 'InvalidTotalSettlementAmount'){
                    alert('Settlement state has changed while you are editing, Please try again.');
                }else if(rst.type == 'CannotCreateToken'){
                    alert('System busy, please try again in a few minutes');
                    location.href = '<?=$this->Url->build(['action'=>'index' ])?>';
                    return;
                }else if(rst.type == 'TotalSettlementAmountLowerThanZero'){
                    alert('Total settlement amount is equal to / lower than zero.');
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

    $('body').on("click", ".btn-tx-hold" , function selectRow(e) {
        var $row = $(this).closest("tr");
        var $elm = $(this);
        var $grid = $(this).closest('.k-grid');
        var grid = $grid.data('kendoGrid')

        var dataItem = grid.dataItem($row);

        if(!dataItem.id){
            return;
        }
        // Stop here if user click "no" in the alert box.
        if(!confirm('The transaction will be removed from the settlement batch.')){
            return;
        }
        holdTxFromBatch(dataItem.id);
        
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
                showSalesPaymentTransaction(e, 'sales', e.data.currency);
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
        var oldData = {id: data.id};

        if(templateName){
            var $win = $("[data-template="+templateName+"].template").clone().removeClass('.template');
            var windowInstance = $win.kendoWindow({
                title: "Editing",
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
                oldData[ fieldName ] = val;
                $win.find('[data-field='+fieldName+']').val( val)
            }
            $win
            .on('change', '[data-field]', function(){
                var fieldName = $(this).data('field');
                var fieldType = $(this).prop('type');
                var val = $(this).val();
                if(fieldType == 'number'){
                    if(!val.length || val.length < 1) val = '0';
                }
                newData [ fieldName ] = val;
            })
            .on('click','.btn-cancel', function(evt){
                evt.preventDefault();
                // Update grid for using original data 
                particularsDs.pushUpdate(oldData)

                for(var i = 0 ; i < fields.length; i ++){
                    var fieldName = fields[i];
                    particularsPart [fieldName]  = oldData[fieldName];
                }

                windowInstance.close();
            })
            .on('click','.btn-confirm', function(evt){
                evt.preventDefault();

                for(var i = 0 ; i < fields.length; i ++){
                    var fieldName = fields[i];
                    particularsPart [fieldName]  = newData[fieldName];
                }

                $win.find('button').hide()
                $win.find('input[type=text]').prop('readonly', true)
                
                // Update grid
                particularsDs.pushUpdate(newData)

                // Submit the update
                submitParticularChange(particularsPart, function(error){

                    if(error){

                        $win.find('input[type=text]').prop('readonly', false)
                        $win.find('button').show()
                        return;
                    }
                    windowInstance.close();
                })
            })
        }
    })



    $('body')
    .on('click', '.btn-recal-summary', function(){
        recalculateSummary();
    })
    .on('click', '.btn-reload-summary', function(){
        if(!confirm('The modifications in the current session will be lost.'))return;
        showIndicator();
        fetchSummary(function(){
            updateFooterMessage();
            hideIndicator();
        });
    })
    .on('click', '.btn-download-batch', function(){
        downloadBatch();
    })
    .on('click', '.btn-submit-batch', function(){
        // if(!confirm('The Settlement Amount will be deducted from the merchant balance as Settlement.'))return;
        submitBatch();
    })

    var today = kendo.date.today();

    // var reportDateDp = $("[name=report_date]").kendoDatePicker({
    //     format: "yyyy-MM-dd",
    // }).data("kendoDatePicker");

    // reportDateDp.value(today)
    // reportDateDp.min(today)
    
    showIndicator();
    fetchSummary( function(error){
        // Initial data load and select all transaction by default.
        if(error) {
            hideIndicator();
            console.log(error)
            return;
        }
        txAllDs.fetch().then(function(){
            var allData = txAllDs.data(); 

            isTxQueried = true;
            totalTx = allData.length;

            refreshDsForFields(txDateDs, 'reconciled_state_time', allData);

            updateFooterMessage();
            hideIndicator();
            txDs.fetch()
        })
    });
    

})
</script>
<style>
@import url(<?=$this->Url->css('wc-extra')?>);

#grid-particulars{height:600px;}
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
                <label for="converted_amount">Converted Amount</label>
            </div>
            <div class="k-edit-field">
                <input type="number" name="converted_amount" data-field="converted_amount" value="0" style="width: 100%;" />
            </div>


            <div style="margin:10px 20px;">
            <label for="remarks"><?=__('Remarks')?></label>
            <textarea name="remarks" data-field="remarks" style="width: 95%; height:100px;"></textarea>
            </div>


            <div class="k-edit-buttons k-state-default">
                <button type="button" class="k-primary k-button btn-confirm">Confirm</button>
                <button type="button" class=" k-button btn-cancel">Cancel</button>
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
                <button type="button" class="k-primary k-button btn-confirm">Confirm</button>
                <button type="button" class="k-button btn-cancel">Cancel</button>
            </div>
        </div>
    </div>
    
</div>