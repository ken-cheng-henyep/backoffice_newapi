<div class="users form search-input">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>

<?= $this->Form->create(null, ['url' => ['controller' => 'SettlementProcess', 'action' => 'submit'],'name'=>'main-search' ]) ?>
    <fieldset>
        <legend><?= __('Processor Reconciliation') ?></legend>
        <ul class="fieldlist">
            <li  class="row label-left">
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('start_date',['type' => 'text','id'=>'tbStartDate', 'required' => true, 'label'=>'From' ]) ?>
                </div>
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('end_date',['type' => 'text','id'=>'tbEndDate', 'required' => true,'label'=>'To' ]) ?>
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

    <h5>A: Amount received from processor</h5>
    <div id="grid-acquirer"></div>
    <div class="clearfix"></div>

    <h5>B: Amount add to merchant balance</h5>
    <div id="grid-merchant"></div>
    <div class="clearfix"></div>

    <div class="btn-wrap">
    <button type="button" class="btn-download k-button" disabled="">Download</button>
    <button type="button" class="btn-confirm k-button" disabled="">Confirm</button>
</div>

    <h5>C: Transaction details</h5>
    <div>Processor: <span class="filter-label filter-processor">All</span></div>
    <div>Merchant: <span class="filter-label filter-merchant">All</span></div>
    <div id="grid-transaction"></div>
    <div class="clearfix"></div>
    <div class="btn-wrap">
    <button type="button" class="btn-show-add-tx-win k-button" disabled="">Add</button>
</div>
    <p>&nbsp;</p>
    <p>&nbsp;</p>
    <p>&nbsp;</p>
</div>

<div style="display:none;">

<div class="win-add-tx template">

<?= $this->Form->create(null, ['url' => ['action' => 'fetchInfo'] ]) ?>
    <fieldset>
        <legend><?= __('Add Transaction') ?></legend>
        <div>Select additional transaction from:</div>
        <div>&nbsp;</div>
    <div class="clearfix"></div>
        <ul class="fieldlist">
            <li  class="row label-left">
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('start_date',['type' => 'text','id'=>'tbStartDate', 'required' => true, 'label'=>'From' ]) ?>
                </div>
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('end_date',['type' => 'text','id'=>'tbEndDate', 'required' => true,'label'=>'To' ]) ?>
                </div>
            </li>
        </ul>
    <div class="clearfix"></div>
    <div class="btn-wrap">
<?= $this->Form->button(__('Search'), ['type' => 'submit', 'class'=>'k-button']); ?>
</div>
    </fieldset>
<?= $this->Form->end() ?>    
    
    <div class="wrap">
    <div class="grid"></div>
       
    <div class="btn-wrap">
<?= $this->Form->button(__('Add'), ['type' => 'button', 'class'=>'btn-confirm k-button']); ?>
</div>
    </div>
    </div>
</div>



<script>
$(function() {

    var selectedMerchant;
    var selectedProcessor;

    var searchInfo = {start_date: null, end_date: null, txids:[], checksum: null};

    var requestColumnData = false;

    // Request data-set for first search for reconcile
    var batchQueryData = function(){

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
            start_date_ts: start_date, 
            end_date_ts: end_date
        };


<?php if(isset($debug) && $debug == 'yes'):?>
        formData.debug = 'yes';
<?php endif;?>
        return formData;
    };


    // Request data-set for submitting tx search
    var fetchQueryData = function(){
        return {
            txid: searchInfo.txids.join(',')
        };
    }

    // Request data-set for submitting reconsilication batch
    var submitData = function(){

        return { 
            checksum: searchInfo.checksum,
            start_date_ts: searchInfo.start_date, 
            end_date_ts: searchInfo.end_date,
            txid: searchInfo.txids.join(',')
        };
    }

    // For new date range search, reset all found / arranged setting 
    var queryTx = function(){

        // Update the flag for asking column data
        requestColumnData = true;

        // Hack: reset all filterable selection
        // $("form.k-filter-menu button[type='reset']").trigger("click");

        transactionDs.data([]);
        transactionGridDs.data([]);

        merchantDs.data([]);
        acquirerDs.data([]);

        // Reset transaction view type to null
        txGridViewFilter = null;

        var postData = batchQueryData();
        // console.log(start_date, end_date);

        $('.btn-download, .btn-confirm, .btn-show-add-tx-win').prop('disabled', true)

        showIndicator();

        // Ask server to provide calculated result.
        $.post('<?=$this->Url->build([ "action" => "fetchInfo" ])?>', postData, function(rst){
            hideIndicator();

            searchInfo.start_date = postData.start_date_ts;
            searchInfo.end_date = postData.end_date_ts;

            if(rst.txids)
                searchInfo.txids = rst.txids;
            if(rst.data)
                transactionDs.data(rst.data );
            if(rst.acquirers){
                acquirerDs.data(rst.acquirers );
            }
            if(rst.merchants){
                merchantDs.data(rst.merchants );
            }


            // Use this value to confirm is the submitted result is matched from database
            if(rst.checksum)
                searchInfo.checksum = rst.checksum;


            $('.btn-show-add-tx-win').prop('disabled', false)
            $('.btn-download, .btn-confirm').prop('disabled', searchInfo.txids.length < 1)

        }, 'json').error( function(err){
            hideIndicator();

            $('.btn-show-add-tx-win').prop('disabled', false)
            $('.btn-download, .btn-confirm').prop('disabled', searchInfo.txids.length < 1)
        })
    }

    // Search transaction by a set of transaction log id (id)
    var reloadTxLoader = null;
    var reloadTx = function(){
        
        // If any previous loader is not finished, kill it and reload again with new data request.
        if(reloadTxLoader)
            reloadTxLoader.abort();

        $('.btn-download, .btn-confirm, .btn-show-add-tx-win').prop('disabled', true)

        reloadTxLoader = null;
        if( searchInfo.txids.length < 1){
            
            $('.btn-show-add-tx-win').prop('disabled', false)

            transactionDs.data([]);
            acquirerDs.data([]);
            merchantDs.data([]);
            searchInfo.checksum = '';
            currencyDs.data([])
            refreshTransactionGrid();

            transactionStateDs.data([])
            transactionMerchantDs.data([])
            transactionMerchantGroupDs.data([])
            transactionProcessorNameDs.data([])
            transactionCurrencyDs.data([])

            return;   
        }

        // Show the loading bar at the screen.
        showIndicator();

        var postData = fetchQueryData();

        postData.ignore_settlement_status = 'yes';

         // Ask server to provide calculated result.
        reloadTxLoader = $.post('<?=$this->Url->build([ "action" => "fetchInfo" ])?>', postData, function(rst){

            hideIndicator();


            if(rst.txids)
                searchInfo.txids = rst.txids;
            if(rst.data)
                transactionDs.data(rst.data );
            if(rst.acquirers){
                acquirerDs.data(rst.acquirers );
            }
            if(rst.merchants){
                merchantDs.data(rst.merchants );
            }
            if(rst.currencies){
                currencyDs.data(rst.currencies );
            }

            if(rst.txids_changed){
                alert('Settlement state has changed while you are editing.')
            }


            $('.btn-show-add-tx-win').prop('disabled', false)
            $('.btn-download, .btn-confirm').prop('disabled', searchInfo.txids.length < 1)

            // Use this value to confirm is the submitted result is matched from database
            if(rst.checksum)
                searchInfo.checksum = rst.checksum;

            reloadTxLoader = null;
            refreshTransactionGrid();

        }, 'json').error( function(err){
            hideIndicator();

            $('.btn-show-add-tx-win').prop('disabled', false)
            $('.btn-download, .btn-confirm').prop('disabled', searchInfo.txids.length < 1)

            reloadTxLoader = null;
        })
    }

    // Storage for all transaction data. Use it's transaction log id to handle include / exclude settlement data set
    var transactionDs = new kendo.data.DataSource({
        data: [],
        sort: { field: "state_time", dir: "desc" },

        model: {
            id: "txid",
            fields: {
                txid: {
                    //this field will not be editable (default value is true)
                    editable: false,
                    // a defaultValue will not be assigned (default value is false)
                    nullable: true
                },

            }
        }
    });

    var transactionStateDs = new kendo.data.DataSource({
        sortable: true, 
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: {field: 'state', dir:'asc'}
    });

    var transactionMerchantGroupDs = new kendo.data.DataSource({
        sortable: true, 
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: {field: 'merchantgroup_name', dir:'asc'}
    });

    var transactionMerchantDs = new kendo.data.DataSource({
        sortable: true, 
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: {field: 'merchant_name', dir:'asc'}
    });

    var transactionProcessorNameDs = new kendo.data.DataSource({
        sortable: true, 
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: {field: 'processor_name', dir:'asc'}
    });

    var transactionCurrencyDs = new kendo.data.DataSource({
        sortable: true, 
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: {field: 'currency', dir:'asc'}
    });

    var transactionCustomerNameDs = new kendo.data.DataSource({
        sortable: true, 
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: {field: 'customer_name', dir:'asc'}
    });

    var transactionEmailDs = new kendo.data.DataSource({
        sortable: true, 
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: {field: 'email', dir:'asc'}
    });


    var currencyDs = new kendo.data.DataSource({
        sortable: true, 
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: {field: 'currency', dir:'asc'}
    });

    var transactionColumns = [
        {'field':'state_time','title':'State Time', width: 200, sortable: true, filterable: false},
        {'field':'state','title':'Trans Type', width: 100, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionStateDs }},
        {'field':'customer_name','title':'Customer', width: 200, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionCustomerNameDs }},
        {'field':'email','title':'Email', width: 280, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionEmailDs }},
        {'field':'merchantgroup_name','title':'Merchant', width: 300, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionMerchantGroupDs }},
        {'field':'merchant','title':'Account', width: 360, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionMerchantDs }},

        {'field':'processor_name','title':'Processor', width: 300, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionProcessorNameDs }},
        {'field':'currency','title':'P. Currency', width:160, sortable: true, filterable: {checkAll: true, multi: true, dataSource: transactionCurrencyDs}},
        {'field':'amount','title':'Amount', width: 100, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'processor_fee','title':'Processor Fee', width: 100, sortable: false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'processor_net_amount','title':'Net Amount', width: 100, sortable: false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'merchant_ref','title':'Merchant Ref', width: 300, sortable: true, filterable: false},
        {'field':'transaction_id','title':'Transaction Id', width: 320, sortable: true, filterable: false},
        {'field':'product','title':'Product', width: 200, sortable: true, filterable: false},
        {'field':'ip_address','title':'IP Address', width: 120, sortable: true, filterable: false},
        {
            field: "action",
            title: "Actions",
            width: 140,
            sortable: false,
            filterable: false,
            template: "<a class='btn-exclude-tx' href=\"javascript:void(0);\" data-id='${id}' data-type=\"transaction\">Exclude</a>"
        }
    ];
    // Datasource for showing in transaction grid during by filters (txGridViewFilter)
    var transactionGridDs = new kendo.data.DataSource({
        pageSize: 25,
        sortable: true,
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: { field: "state_time", dir: "desc" },

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

    // Datasource of selected acquirers
    var acquirerDs  = new kendo.data.DataSource({
        sortable: true,
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],

        sort: { field: "name", dir: "asc" },
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
    });;


    // Define Grid UI Columns
    var acquirerColumns = [
        {
            'field':'name',
            'title':'Processor', 
            width: 300, 
            sortable: true, 
            filterable: {checkAll: true, multi: true, dataSource: acquirerDs},
        },
        {'field':'currency','title':'P. Currency', width:160, sortable: true, filterable: false},
        {'field':'amount','title':'Amount', width: 160, sortable: true, groupable:false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'count','title':'Count', width: 160, sortable: true, groupable:false, filterable: false, attributes:{style:"text-align:right;"}},
        {'field':'fee','title':'Fee', width: 160, sortable: true, groupable:false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'net_amount','title':'Net Amount', width: 160, sortable: true, groupable:false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {
            field: "action",
            title: "Actions",
            width: 200,
            sortable: false,
            filterable: false,
            template: "<a class='btn-view-tx' href=\"javascript:void(0);\" data-name='${name}' data-currency='${currency}' data-id='${id}' data-type=\"acquirer\">View</a> | <a class='btn-exclude-tx' href=\"javascript:void(0);\" data-name='${name}' data-id='${id}' data-type=\"acquirer\">Exclude</a>"
        }
    ];

    $("#grid-acquirer").kendoGrid({
        columns: acquirerColumns,
        dataSource: acquirerDs,
        selectable: "row",
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        height: 300,
        noRecords: true,
        filterable: {
            extra: false
        },
        pageable: false,
        
        resizable: true,
        messages: {
            noRecords: "No record found."
        },
    });
    var acquirerGrid =  $('#grid-acquirer').data('kendoGrid');

    // Datasource of selected merchants
    var merchantDs = new kendo.data.DataSource({
        sortable: true,
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: { field: "name", dir: "asc" },
        columns:[{
            field: "id", 
            groupHeaderTemplate: "#=items[0].name#"
        }],
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
    var merchantColumns = [
        {'field':'name','title':'Merchant', width: 400, sortable: true, filterable: {checkAll: true, multi: true, dataSource: merchantDs,
            groupHeaderTemplate: "#= value#"}},
        {'field':'currency','title':'P. Currency', width:160, sortable: true, filterable: false},
        {'field':'payment_amount','title':'Payment Amount', width: 160, sortable: true, groupable:false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'payment_count','title':'Payment Count', width: 160, sortable: true, groupable:false, filterable: false, attributes:{style:"text-align:right;"}},
        {'field':'payment_fee','title':'Payment Fee', width: 160, sortable: true, groupable:false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'refund_amount','title':'Refund Amount', width: 160, sortable: true, groupable:false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'refund_count','title':'Refund Count', width: 160, sortable: true, groupable:false, filterable: false, attributes:{style:"text-align:right;"}},
        {'field':'refund_fee','title':'Refund Fee', width: 160, sortable: true, groupable:false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'net_amount','title':'Net Amount', width: 160, sortable: true, groupable:false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {
            field: "action",
            title: "Actions",
            width: 200,
            sortable: false,
            filterable: false,
            template: "<a class='btn-view-tx' href=\"javascript:void(0);\" data-name='${name}' data-currency='${currency}' data-id='${id}' data-type=\"merchantgroup\">View</a> | <a class='btn-exclude-tx' href=\"javascript:void(0);\" data-name='${name}' data-id='${id}' data-type=\"merchantgroup\">Exclude</a>"
        }
    ];
    $("#grid-merchant").kendoGrid({
        columns: merchantColumns,
        dataSource: merchantDs,
        groupable: false,
        selectable: "row",
        height: 600,
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        noRecords: true,
        filterable: {
            extra: false
        },
        pageable: false,
        
        resizable: true,
        messages: {
            noRecords: "No record found."
        }
    });
    var merchantGrid =  $('#grid-merchant').data('kendoGrid');


    // Grid of showing selected transaction (filtered by txGridViewFilter)
    $("#grid-transaction").kendoGrid({
        columns: transactionColumns,
        // javascript:void(0);
        // data-id=${id}, data-bid=${batch_id}
        dataSource: transactionGridDs,
        height: 600,
        /*change: onGridChange, */
        selectable: "row",
        groupable: false,
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        filterable: {
            extra: false
        },
        noRecords: false,
        pageable: {
            refresh: false,
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
    var transactionGrid =  $('#grid-transaction').data('kendoGrid');


    var txGridViewFilter = null;

    // A dialog for showing add transaction list 
    var createAddTxDialogContent = function(selectedTxids, completion){

        // Create HTML Dom element from a template        
        var $self = $('.win-add-tx.template').clone();
        $self.removeClass('template');

        var newSelectedTxIds = {};

        var transactionAddColumns = [
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
                    var foundIndex = $.inArray(dataItem.id, newSelectedTxIds);
                    var instanceId = 'cb_'+((Math.random()*1000000)<<0)+'_'+dataItem.id;
                    if(foundIndex < 0)
                       return '<input type="checkbox" class="checkbox k-checkbox" value="'+dataItem.id+'" id="'+instanceId+'"/><label for="'+instanceId+'" class="k-checkbox-label k-no-text"></label>';
                    return '<input type="checkbox" class="checkbox k-checkbox" checked="" value="'+dataItem.id+'" id="'+instanceId+'"/><label for="'+instanceId+'" class="k-checkbox-label k-no-text"></label>';
                }

            },
            {'field':'state_time','title':'State Time', width: 200, sortable: true, filterable: false},
            {'field':'state','title':'Trans Type', width: 80, sortable: true, filterable: false},
            {'field':'customer_name','title':'Customer', width: 200, sortable: true, filterable: true},
            {'field':'email','title':'Email', width: 280, sortable: true, filterable: true},
            {'field':'merchantgroup_name','title':'Merchant', width: 300, sortable: true, filterable: false},
            {'field':'merchant','title':'Account', width: 360, sortable: true, filterable: false},
            {'field':'processor_name','title':'Processor', width: 300, sortable: true, filterable: false},
            {'field':'currency','title':'P. Currency', width:160, sortable: true, filterable: false},
            {'field':'amount','title':'Amount', width: 100, sortable: true, filterable: true, format: "{0:n2}", attributes:{style:"text-align:right;"}},
            {'field':'processor_fee','title':'Processor Fee', width: 100, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
            {'field':'net_amount_processor','title':'Net Amount', width: 100, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
            {'field':'merchant_ref','title':'Merchant Ref', width: 300, sortable: true, filterable: false},
            {'field':'transaction_id','title':'Transaction Id', width: 320, sortable: true, filterable: false},
            {'field':'product','title':'Product', width: 300, sortable: true, filterable: false},
            {'field':'ip_address','title':'IP Address', width: 140, sortable: true, filterable: false}
        ];

        var gridDs = new kendo.data.DataSource({
            transport: {
                read: {
                    // the remote service url
                    url: '<?=$this->Url->build([ "action" => "fetchInfo" ])?>',

                    // the request type
                    type: "post",

                    // the data type of the returned result
                    dataType: "json",

                    // additional custom parameters sent to the remote service
                    data: function(){
                        return buildQueryData();
                    }
                }
            },
            pageSize: 25,
            sortable: true,
            serverFiltering: false,
            serverPaging: false,
            serverSorting: false,
            data: [],
            sort: { field: "state_time", dir: "desc" },
            change: function() { 
                $self.find('button').prop('disabled', false)
                // subscribe to the CHANGE event of the data source
            },
            schema: {
                errors: "error",
                data: "data",
                total: "total"
            },
            model: {
                id: "txid",
                fields: {
                    txid: {
                        //this field will not be editable (default value is true)
                        editable: false,
                        // a defaultValue will not be assigned (default value is false)
                        nullable: true
                    },

                }
            }
        });

        /**
         * Select all checkbox
         */
        var updateSelectAll = function(){
            if(grid){
                $self.find('.grid [role=columnheader] .checkbox')[0].checked = grid.items().find(":checked").length == grid.dataSource.view().length && grid.dataSource.view().length > 0;
            }
        }

        /**
         * Builds a query data.
         *
         * @return     {<type>}  The query data.
         */
        var buildQueryData = function(){


            var start_date = _startDateDp && _startDateDp.value()? _startDateDp.value().getTime() : null;
            var end_date = _endDateDp && _endDateDp.value() ? _endDateDp.value().getTime() : null;


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
                start_date_ts: start_date, 
                end_date_ts: end_date
            };

            // Exclude all added transaction log id
            formData.exclude_txid = selectedTxids.join(',');

            if(txGridViewFilter != null){
                if( txGridViewFilter.type == 'acquirer'){
                    formData.acquirer_mid = txGridViewFilter.value;
                }

                if( txGridViewFilter.type == 'merchantgroup'){
                    formData.merchantgroups = txGridViewFilter.value;
                }
            }


    <?php if(isset($debug) && $debug == 'yes'):?>
            formData.debug = 'yes';
    <?php endif;?>

            return formData;
        };

        /**
         * Queries a transmit.
         */
        var queryTx = function(){
            $self.find('button').prop('disabled', true)
            // gridDs.data([]);
            gridDs.read();

            // $self.find('button').prop('disabled', true)

            // var postData =  buildQueryData();

            // // Ask server to provide calculated result.
            // $.post('<?=$this->Url->build([ "action" => "fetchInfo" ])?>',postData, function(rst){
            //     if(rst.data)
            //         gridDs.data(rst.data );

            //     $self.find('button').prop('disabled', !true)
            // }, 'json').error( function(err){

            //     $self.find('button').prop('disabled', !true)
            // })
        }

        $self.find('.grid').kendoGrid({
            columns: transactionAddColumns,
            dataSource: gridDs,
            persistSelection: true,
            selectable: 'multiple, row',
            sortable: {
                mode: "single",
                allowUnsort: true
            },
            filterable: {
                extra: false
            },
            noRecords: true,
            messages: {
                noRecords: "No record found."
            },
            change: function (e, args) {
                var grid = e.sender;
                var items = grid.items();

                items.each(function (idx, row) {
                    var dataItem = grid.dataItem(row)

                    var found = typeof newSelectedTxIds[ dataItem.id ] != 'undefined';
                    if( found ){
                        grid.tbody.find("tr[data-uid='" + dataItem.uid + "']")
                        .addClass("k-state-selected")
                        .find(".checkbox")
                        .attr("checked","checked");
                    }
                });

                updateSelectAll();
            },
            dataBound: function onDataBound(e) {
                var view = this.dataSource.view();
                // e.sender.items().each(function(){
                //     var dataItem = e.sender.dataItem(this);
                //     kendo.bind(this, dataItem);

                // })


                // Select all selected items during paging data loaded/binded to rows
                for(var idx = 0; idx < view.length; idx ++){
                    var dataItem = view[idx];

                    // console.log(dataItem , dataItem.uid , typeof newSelectedTxIds[ dataItem.id ] != 'undefined')
                    var found = typeof newSelectedTxIds[ dataItem.id ] != 'undefined';
                    if( found ){
                        grid.tbody.find("tr[data-uid='" + dataItem.uid + "']")
                        .addClass("k-state-selected")
                        .find(".checkbox")
                        .attr("checked","checked");
                    }
                }
                updateSelectAll();
            },
            pageable: {
                refresh: false,
                //pageSizes: true,
                pageSizes: [10, 25, 50, "All"],
                buttonCount: 2
            },
            messages: {
                noRecords: "No record found."
            },
            
            resizable: true,
            //scrollable: false,
        });

        $self.on("change", "[role=columnheader] .checkbox" , function selectAllRows() {
            var checked = this.checked;

            var view = gridDs.view();

            for(var idx = 0; idx < view.length; idx ++){

                var dataItem = view[idx];

                var found = typeof newSelectedTxIds[ dataItem.id ] != 'undefined';

                if( checked ){
                    // If checked but not found in the list
                    if(!found)
                        newSelectedTxIds[dataItem.id] = true;
                }else{
                    // If unchecked but found in the list
                    if(found)
                        delete newSelectedTxIds[dataItem.id];
                }

                // console.log($row);

                // // If the ui presented in grid, trigger click event for update ui
                // if($row.length){
                //     $row.trigger('update');
                // }
            }

            grid.table.find('[role=row]').each(function(){
                $(this).trigger('update');
            })
        });
        var grid = $self.find('.grid').data("kendoGrid");
        grid.table
        .on("change", ".checkbox" , function selectRow() {
            var checked = this.checked,
            row = $(this).closest("tr");


            dataItem = grid.dataItem(row);

            var found = typeof newSelectedTxIds[ dataItem.id ] != 'undefined';

            if( checked ){
                // If checked but not found in the list
                if(!found)
                    newSelectedTxIds[dataItem.id] = true;
            }else{
                // If unchecked but found in the list
                if(found)
                    delete newSelectedTxIds[dataItem.id];
            }

            row.trigger('update');

            updateSelectAll();
        })
        .on('update', '[role=row]', function updateRow(){
            var row = $(this);
            var dataItem = grid.dataItem(row);

            var checked = typeof newSelectedTxIds[ dataItem.id ] != 'undefined';


            if (checked) {
                //-select the row
                row.addClass("k-state-selected");
                if(!row.find('.checkbox').prop('checked'))
                    row.find('.checkbox').prop('checked', true)
            } else {
                //-remove selection
                row.removeClass("k-state-selected");
                if(row.find('.checkbox').prop('checked'))
                    row.find('.checkbox').prop('checked', !true)
            }
        })
        // ;


        var today = kendo.date.today();



        // Handle date picker within dialog
        function _startChange() {
            var startDate = _startDateDp.value(),
            endDate = _endDateDp.value();

            if (startDate) {
                startDate = new Date(startDate);
                startDate.setDate(startDate.getDate());
                _endDateDp.min(startDate);
            } else if (endDate) {
                _startDateDp.max(new Date(endDate));
            } else {
                endDate = new Date();
                _startDateDp.max(endDate);
                _endDateDp.min(endDate);
            }
        }

        function _endChange() {
            var endDate = _endDateDp.value(),
            startDate = _startDateDp.value();

            if (endDate) {
                endDate = new Date(endDate);
                endDate.setDate(endDate.getDate());
                _startDateDp.max(endDate);
            } else if (startDate) {
                _endDateDp.min(new Date(startDate));
            } else {
                endDate = new Date();
                _startDateDp.max(endDate);
                _endDateDp.min(endDate);
            }
        }

        // Copy search range from main search form
        $self.find("[name=start_date]").val( $('[name=main-search] [name=start_date]').val() )
        $self.find("[name=end_date]").val( $('[name=main-search] [name=end_date]').val() )


        // Setup selection range
        var _startDateDp = $self.find("[name=start_date]").kendoDatePicker({
            format: "yyyy-MM-dd",
            change: _startChange
        }).data("kendoDatePicker");


        var _endDateDp = $self.find("[name=end_date]").kendoDatePicker({
            format: "yyyy-MM-dd",
            change: _endChange
        }).data("kendoDatePicker");

        _startDateDp.max(today);
        _endDateDp.max(today);

        $self.on('submit', 'form', function(evt){
            evt.preventDefault();

            queryTx();
        })

        $self.on('click', '.btn-confirm', function(evt){
            evt.preventDefault();

            // Translate dictionary into array.
            var ary = [];
            var data = [];
            for(var txId in newSelectedTxIds){

                ary.push(txId);
                data.push( gridDs.get(txId) );
            }

            var rst = {
                selectedIds: ary,
                data: data,
            };

            // Send the callback 
            if(completion) completion(rst)

            // hide the dialog
        })

        return {$elm: $self};
    }

    /**
     * Shows the add transaction dialog.
     */
    var showAddTxDialog = function(){
        var winInstance = null;

        var completionCallback = function(rst){

            // If dialog return final added
            if(rst.selectedIds){
                for(var k = 0; k< rst.selectedIds.length; k ++){
                    var found =-1;

                    // Search if the value existing in current selected array
                    for(var i = 0; i< searchInfo.txids.length; i ++){
                        if( searchInfo.txids[i] == rst.selectedIds[k])
                            found = i;
                    }

                    // If the value does not found in existing array, add it there
                    if(found < 0)
                        searchInfo.txids.push(rst.selectedIds[k]);
                }

                // Reload local data source after adjustment
                reloadTx();
            }

            winInstance.close();
        };

        var contentInstance = createAddTxDialogContent(searchInfo.txids, completionCallback)

        winInstance = createWindow('add-tx-'+(new Date()).getTime(),contentInstance.$elm);


        winInstance.api.center().open();
    }

    /**
     * Error handler
     *
     * @param      {Object}  rst     The result
     */
    var errorHandler = function (rst)
    {
        if(rst.status == 'error'){
            if(rst.type == 'MasterMerchantWalletNotFound' || rst.type == 'MasterMerchantNotFound' || rst.type == 'MasterMerchantWalletNotConfigured'){
                alert('Merchant primary settlement wallet does not exist.\n\nMerchant: '+rst.merchantgroup.name+'\nCurrency: '+rst.currency);
                return;
            }
            if(rst.type == 'TokenUsed'){
                alert('System busy, please try again in a few minutes');
                return;
            }
            if(rst.type == 'CannotCreateToken'){
                alert('System busy, please try again in a few minutes');
                return;
            }
            if(rst.type == 'InvalidChecksum' || rst.type == 'InvalidTx'){
                alert('Settlement state has changed while you are editing. Please try again.');
                return;
            }
            if(rst.type == 'MixedCurrencyNotSupported'){
                alert('Multiple currencies are not supported in same reconciliation batch.');
                return;
            }
            if(rst.msg) {
                alert(rst.msg);
                return;
            }

            alert('Undefined error - ' +rst.type);
        }
    }

    /**
     * Group values and store in a DataSource instance
     *
     * @param      {DataSource}  ds          The instance of DataSource object for storing grouped values.
     * @param      {String}  fieldName   The field name from source data
     * @param      {DataSource}  sourceData  The instance of DataSource as source data.
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

    var refreshTransactionGrid = function()
    {
        var view = [];

        $('.filter-processor').text('All')
        $('.filter-merchant').text('All')

        var filters = [];
        if(txGridViewFilter != null){
            if(txGridViewFilter.type == 'acquirer'){
                filters.push({ field: "acquirer_mid", operator: "equal", value: txGridViewFilter.value } );
                if(txGridViewFilter.currency)
                    filters.push({ field:'currency', operator: 'equal', value: txGridViewFilter.currency })
                $('.filter-processor').text(txGridViewFilter.name)
            }
            if(txGridViewFilter.type == 'merchantgroup'){
                filters.push({ field: "merchantgroup_id", operator: "equal", value: txGridViewFilter.value } );
                if(txGridViewFilter.currency)
                    filters.push({ field:'currency', operator: 'equal', value: txGridViewFilter.currency })
                $('.filter-merchant').text(txGridViewFilter.name)
            }
        }
        transactionDs.filter(filters) ;
        view = transactionDs.view();
        $("#grid-transaction form.k-filter-menu button[type='reset']").trigger("click");

        transactionGridDs.data( view );
        transactionGridDs.page(1);
        

        refreshDsForFields(transactionCustomerNameDs, 'customer_name', view);
        refreshDsForFields(transactionEmailDs, 'email', view);
        refreshDsForFields(transactionCurrencyDs, 'currency', view);
        refreshDsForFields(transactionProcessorNameDs, 'processor_name', view);
        refreshDsForFields(transactionMerchantDs, 'merchant', view);
        refreshDsForFields(transactionMerchantGroupDs, 'merchantgroup_name', view);
        refreshDsForFields(transactionStateDs, 'state', view);
    }

    // Getting last settlement date by querying holiday
    $.getJSON('<?=$this->Url->build([ "controller"=>"Holidays", "action" => "lastBusinessDateFromDate" , 't'=>1, ])?>', function(rst){
        if(!rst.allowed){
            console.log('Today is not the default settlement process day. Start / end date will not be assigned.');
            console.log(rst);
        }else{

            $("[name=main-search] [name=start_date]").attr('value', rst.range_start.date.substr(0,10));
            $("[name=main-search] [name=end_date]").attr('value', rst.range_end.date.substr(0,10));

            startDateDp.value(  new Date(rst.range_start.date)  );
            startChange()
            endDateDp.value(  new Date(rst.range_end.date)  );
            endChange();
        }
    })

    function excludeTx(txid)
    {
        var found = -1;
        for(var i = 0; i< searchInfo.txids.length; i ++){
            if( searchInfo.txids[i] == txid ){
                found = i;
                break;
            }
        }

        // If any item found
        if( found >= 0){
            searchInfo.txids.splice(found, 1);
            return true;
        }
        return false;
    }

    // Handle "View" button action in merchant / acquirer grid view
    $('.users').on('click', '.btn-view-tx', function(evt){
        evt.preventDefault();

        var data = $(this).data();
        var view = null;

        if(data.type == 'acquirer'){
            txGridViewFilter = {type: data.type, value: data.id, name: data.name, currency: data.currency};
            refreshTransactionGrid();
        }
        if(data.type == 'merchantgroup'){
            txGridViewFilter = {type: data.type, value: data.id, name: data.name, currency: data.currency};
            refreshTransactionGrid();
        }
    })

    // Handle "Add" button action 
    $('.users').on('click', '.btn-show-add-tx-win', function(evt){
        evt.preventDefault();

        if(!searchInfo.start_date || !searchInfo.end_date){
            alert('Please submit your date range before adding transactions.');
            return;
        }
        showAddTxDialog();
    })

    // Handle "Exclude" button action in transaction grid view
    $('.users').on('click', '.btn-exclude-tx', function(evt){
        evt.preventDefault();

        var data = $(this).data();
        if(data.type == 'acquirer'){
            
            var view;
            transactionDs.filter( { field: "acquirer_mid", operator: "equal", value: data.id }) ;
            view = transactionDs.view();

            $.each(view, function(idx, dataItem){
                excludeTx(dataItem.id);
            })
            reloadTx();
        }
        if(data.type == 'merchantgroup'){

            var view;
            transactionDs.filter( { field: "merchantgroup_id", operator: "equal", value: data.id }) ;
            view = transactionDs.view();

            $.each(view, function(idx, dataItem){
                excludeTx(dataItem.id);
            })
            reloadTx();
        }
        if( data.type == 'transaction'){
            var txid = data.id;
            if( excludeTx(txid)){
                reloadTx();
            }
        }
    })

    // Handle data search form
    $('.users.form form').on('submit', function(evt){
        evt.preventDefault();

        queryTx();
        // dataSource.read();
    })

    // Handle download button action 
    $('.users.form .btn-download').on('click', function(){
        if( searchInfo.txids.length < 1){
            alert('Please select any transaction.');
            return;
        }
        var data = fetchQueryData();
        data.start_date= kendo.toString(new Date(searchInfo.start_date), 'yyyy-MM-dd');
        data.end_date= kendo.toString(new Date(searchInfo.end_date), 'yyyy-MM-dd');

        $.ajax({
            url:'<?=$this->Url->build([ "action" => "download" ])?>',
            data: data,
            type:'post',
            dataType:'json',
            success: function(rst){
                if(rst.path){
                    location.href= (rst.path);
                }
            },
            error: function(){
                alert('Sorry, server cannot handle')
            }
        })
    })

    // Handle confirm button action 
    $('.users.form .btn-confirm').on('click', function(){
        if( searchInfo.txids.length < 1){
            alert('Please select any transaction.');
            return;
        }
        if(!confirm('The Net Amount in Area B will be added to the merchant balance as EOD payment processing.'))return;


        $('.btn-download, .btn-confirm, .btn-show-add-tx-win').prop('disabled', true)
        showIndicator()
        $.ajax({
            url:'<?=$this->Url->build([ "action" => "submit" ])?>',
            data: submitData(),
            type:'post',
            dataType:'json',
            success: function(rst){
                hideIndicator()
                if(rst && rst.status == 'done'){
                    alert('Reconciliation completed.');
                    //location.href = '<?=$this->Url->build([ ])?>';
                    //
                    resetSearch();
                    return;
                }else{
                    $('.btn-download, .btn-confirm, .btn-show-add-tx-win').prop('disabled', false)
                    errorHandler(rst);
                }
            },
            error: function(){
                hideIndicator()
                $('.btn-download, .btn-confirm, .btn-show-add-tx-win').prop('disabled', false)
                alert('Sorry, server cannot handle')
            }
        })
    })

    $('.users.form [type=reset]').on('click', function(){
        if(!searchInfo.txids.length) return;
        if(!confirm('All of exist setting will be discard. Confirm to reset?'))return;
        resetSearch();
    })

    var $activityIndicator = $('<div class="k-loading-mask fixed"><span class="k-loading-text">Loading...</span><div class="k-loading-image"><div class="k-loading-color"></div></div></div>');

    /**
     * Shows the indicator.
     */
    function showIndicator()
    {
        $('.users.form').css('position','relative');
        $activityIndicator.appendTo('.users.form');
    }

    /**
     * Hides the indicator.
     */
    function hideIndicator()
    {
        $activityIndicator.remove();
    }

    /**
     * Reset the search result
     */
    function resetSearch()
    {
        merchantDs.data([])
        transactionDs.data([])
        acquirerDs.data([])
        searchInfo.txids.length = 0;

        txGridViewFilter = null;

        reloadTx();
        refreshTransactionGrid();
    }

    // Handle date picker
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
});

/**
 * Display message in a popup dialog
 *
 * @param      {String}  msg     The message
 */
function popup(msg) {
    var dialog = $("#errdialog").data("kendoDialog");
    dialog.content(msg);
    dialog.open();
}

/**
 * Creates a window.
 *
 * @param      {String}  id       The identifier
 * @param      {Object}  elm      The DOM / jQuery selected Object 
 * @param      {Object}  options  The options of the window
 * @return     {Object}  A reference to access the created windows and helper functions
 */
function createWindow(id, elm, options) {

    var dfd = new jQuery.Deferred();
    var result = false;

    var _options = $.extend(options, {
        actions: ['Close'],
        width: "80%",
        resizable: true,
        modal: true,
        title: "",
        visible: false
    })

    var $win = $("<div>");
    $win.prop('id', id)
    .appendTo("body")
    .kendoWindow(_options);
    var api = $win.data('kendoWindow');
    api.content(elm)

    return {$win: $win, elm: elm, api: api, close: function(){ api.close();} , open: function(){ api.open();} , promise: dfd.promise()};
  };

</script>
<style>

@import url(<?=$this->Url->css('wc-extra')?>);

.k-multiselect-wrap li{padding-bottom: .1em;}

</style>