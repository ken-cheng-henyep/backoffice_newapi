<div class="users form search-input">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>

<?= $this->Form->create(null, ['url' => [ 'action' => 'search'],'name'=>'main-search' ]) ?>
    <fieldset>
        <legend><?= __('Processor Reconciliation Search') ?></legend>
        <ul class="fieldlist">
            <li  class="row label-left">
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('start_date',['type' => 'text','id'=>'tbStartDate', 'required' => true, 'label'=>'From', 'value'=>date('Y-m-d', strtotime('-14 days')) ]) ?>
                </div>
                <div class="col-md-6 col-lg-4">
                    <?= $this->Form->input('end_date',['type' => 'text','id'=>'tbEndDate', 'required' => true,'label'=>'To', 'value'=>date('Y-m-d') ]) ?>
                </div>
            </li>
        </ul>
    </fieldset>
    <div>
<?= $this->Form->button(__('Search'), ['type' => 'submit', 'class'=>'left']); ?>
    </div>

<?= $this->Form->end() ?>                                                                                                
    <div class="clearfix"></div>                                                                                                                                                                                                                                                                                       
    <h5>Search Result:</h5>                                                                                                               
    <div id="grid-reconciliation"></div>                                                                                                
    <div class="clearfix"></div>

    <h5>A: Amount received from processor</h5>
    <div id="grid-acquirer"></div>
    <div class="clearfix"></div>

    <h5>B: Amount add to merchant balance</h5>
    <div id="grid-merchant"></div>
    <div class="clearfix"></div>

    <div class="btn-wrap">
    <button type="button" class="download-btn k-button">Download</button>
    </div>

    <h5>C: Transaction details</h5>
    <div>Processor: <span class="filter-label filter-processor">All</span></div>
    <div>Merchant: <span class="filter-label filter-merchant">All</span>
    </div>
    <div id="grid-transaction"></div>
    <div class="clearfix"></div>

    <p>&nbsp;</p>
    <p>&nbsp;</p>
    <p>&nbsp;</p>
</div>

<script>
$(function() {

    var selectedMerchant;
    var selectedProcessor;

    var searchInfo = {start_date: null, end_date: null, txids:[], reconciliation_batch_id: null, reconciliation_from_date: null, reconciliation_to_date: null};

    var requestColumnData = false;


    /**
     * { function_description }
     *
     * @return     {Object}  A data-set for first search of reconciliation
     */
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


    /**
     * Fetches a query data.
     *
     * @return     {Object}  A data-set for submitting tx search
     */
    var fetchQueryData = function(){
        return {
            reconciliation_batch_id: searchInfo.reconciliation_batch_id
        };
    }

    /**
     * Queries a batch.
     */
    var queryBatch = function(){

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

        reconciliationGridDs.read();
    }

    /**
     * Queries transactions
     * 
     * new date range search, reset all found / arranged setting 
     */
    var queryTx = function(){

        // Update the flag for asking column data
        requestColumnData = true;

        // Hack: reset all filterable selection
        // $("form.k-filter-menu button[type='reset']").trigger("click");

        transactionGridDs.data([]);
        merchantDs.data([]);
        acquirerDs.data([]);

        // Reset transaction view type to null
        txGridViewFilter = null;

        reloadTx();

    }

    /**
     * Search transaction by a set of transaction log id (tx_id)
     */
    var reloadTx = function(){

        // Reset the supporting data source as empty array 
        transactionProcessorNameDs.data([])
        transactionStateDs.data([])
        transactionMerchantGroupDs.data([])
        transactionMerchantDs.data([])

        transactionDs.read();
    }

    // Datasource of selected acquirers
    var acquirerDs = new kendo.data.DataSource({
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
                }
            }
        }
    });;

    // Define Grid UI Columns
    var acquirerColumns = [
        {'field':'name','title':'Processor', width: 300, sortable: true, filterable: {checkAll: true, multi: true, dataSource: acquirerDs}},
        {'field':'currency','title':'P. Currency', width: 100, sortable: true, filterable: false},
        {'field':'amount','title':'Amount', width: 160, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'count','title':'Count', width: 160, sortable: true, filterable: false, attributes:{style:"text-align:right;"}},
        {'field':'fee','title':'Fee', width: 160, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'net_amount','title':'Net Amount', width: 160, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {
            field: "action",
            title: "Actions",
            width: 200,
            sortable: false,
            filterable: false,
            template: "<a class='btn-view-tx' href=\"javascript:void(0);\" data-name='${name}' data-id='${id}' data-type=\"acquirer\">View</a>"
        },
        {}
    ];

    $("#grid-acquirer").kendoGrid({
        columns: acquirerColumns,
        dataSource: acquirerDs,
        selectable: "row",
        height: 300,
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        filterable: {
            extra: false
        },
        noRecords: true,
        pageable: false,
        
        resizable: true,
        messages: {
            noRecords: "No record found."
        },
        //scrollable: false,
    });
    var acquirerGrid =  $('#grid-acquirer').data('kendoGrid');

    // Storage for all transaction data. Use it's transaction log id to handle include / exclude settlement data set
    var transactionDs = new kendo.data.DataSource({

        sort: { field: "state_time", dir: "desc" },

        schema: {
            errors: "error",
            data: "data",
            total: "total",
            parse: function(rst){
                // debugger;

                if(rst.txids)
                    searchInfo.txids = rst.txids;
                if(rst.acquirers)
                    acquirerDs.data(rst.acquirers );
                if(rst.merchants)
                    merchantDs.data(rst.merchants );
                if(rst.from_date){
                    searchInfo.reconiliation_from_date = rst.from_date;
                }
                if(rst.to_date){
                    searchInfo.reconiliation_to_date = rst.to_date;
                }

                return rst;
            }
        },
        transport: {
            read: {
                // the remote service url
                url: '<?=$this->Url->build([ "action" => "searchBatchTransaction" ])?>',

                // the request type
                type: "post",

                // the data type of the returned result
                dataType: "json",

                // additional custom parameters sent to the remote service
                data: function(){
                    return fetchQueryData();
                }
            }
        },
        model: {
            id: "txid",
            fields: {
                txid: {
                    //this field will not be editable (default value is true)
                    editable: false,
                    // a defaultValue will not be assigned (default value is false)
                    nullable: true
                }
            }
        },
        change: function(){

            hideIndicator()
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
            id: "txid",
            fields: {
                txid: {
                    //this field will not be editable (default value is true)
                    editable: false,
                    // a defaultValue will not be assigned (default value is false)
                    nullable: true
                }
            }
        }
    });

    var transactionColumns = [
        {'field':'state_time','title':'State Time', width: 200, sortable: true, filterable: false},
        {'field':'state','title':'Trans Type', width: 100, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionStateDs }},
        {'field':'customer_name','title':'Customer', width: 200, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionCustomerNameDs }},
        {'field':'email','title':'Email', width: 280, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionEmailDs }},
        {'field':'merchantgroup_name','title':'Merchant', width: 300, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionMerchantGroupDs }},
        {'field':'merchant','title':'Account', width: 360, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionMerchantDs }},

        {'field':'processor_name','title':'Processor', width: 300, sortable: true, filterable: {'multi':true, 'checkAll':true, dataSource: transactionProcessorNameDs }},
        {'field':'currency','title':'P. Currency', width: 100, sortable: true, filterable: {checkAll: true, multi: true, dataSource: acquirerDs}},
        {'field':'amount','title':'Amount', width: 100, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'processor_fee','title':'Processor Fee', width: 100, sortable: false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'processor_net_amount','title':'Net Amount', width: 100, sortable: false, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'merchant_ref','title':'Merchant Ref', width: 300, sortable: true, filterable: false},
        {'field':'transaction_id','title':'Transaction Id', width: 320, sortable: true, filterable: false},
        {'field':'product','title':'Product', width: 200, sortable: true, filterable: false},
        {'field':'ip_address','title':'IP Address', width: 120, sortable: true, filterable: false},
        {}
    ];
    // Grid of showing selected transaction (filtered by txGridViewFilter)
    $("#grid-transaction").kendoGrid({
        columns: transactionColumns,
        dataSource: transactionGridDs,
        selectable: "row",
        height: 600,
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        noRecords: false,
        filterable: {
            extra: false
        },
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
    });
    var transactionGrid =  $('#grid-transaction').data('kendoGrid');

    // Datasource of selected merchants
    var merchantDs = new kendo.data.DataSource({
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

    var merchantColumns = [
        {'field':'name','title':'Merchant', width: 400, sortable: true, filterable: {dataSource: merchantDs, checkAll: true, multi: true}},
        {'field':'currency','title':'P. Currency', width: 100, sortable: true, filterable: false},
        {'field':'payment_amount','title':'Payment Amount', width: 160, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'payment_count','title':'Payment Count', width: 160, sortable: true, filterable: false, attributes:{style:"text-align:right;"}},
        {'field':'payment_fee','title':'Payment Fee', width: 160, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'refund_amount','title':'Refund Amount', width: 160, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'refund_count','title':'Refund Count', width: 160, sortable: true, filterable: false, attributes:{style:"text-align:right;"}},
        {'field':'refund_fee','title':'Refund Fee', width: 160, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {'field':'net_amount','title':'Net Amount', width: 160, sortable: true, filterable: false, format: "{0:n2}", attributes:{style:"text-align:right;"}},
        {
            field: "action",
            title: "Actions",
            width: 200,
            sortable: false,
            filterable: false,
            template: "<a class='btn-view-tx' href=\"javascript:void(0);\" data-name='${name}' data-id='${id}' data-type=\"merchantgroup\">View</a>"
        },
        {}
    ];

    $("#grid-merchant").kendoGrid({
        columns: merchantColumns,
        dataSource: merchantDs,
        selectable: "row",
        height:600,
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        filterable: {
            extra: false
        },
        noRecords: true,
        pageable: false,
        
        resizable: true,
        messages: {
            noRecords: "No record found."
        },
    });
    var merchantGrid =  $('#grid-merchant').data('kendoGrid');


    // Datasource of selected reconciliation
    var reconciliationGridDs = new kendo.data.DataSource({
        sortable: true,
        serverFiltering: false,
        serverPaging: false,
        serverSorting: false,
        data: [],
        sort: { field: "reconcilie_time", dir: "asc" },

        transport: {
            read: {
                // the remote service url
                url: '<?=$this->Url->build([ "action" => "search" ])?>',

                // the request type
                type: "post",

                // the data type of the returned result
                dataType: "json",

                // additional custom parameters sent to the remote service
                data: function(){
                    return batchQueryData();
                }
            }
        },
        schema: {
            errors: "error",
            data: "data",
            total: "total"
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
        },
        change: function(){

            hideIndicator()
        }
    });;

    // Define Grid UI Columns
    var reconciliationColumns = [
        {
            'field':'id',
            'title':'Batch ID', 
            width: 300, 
            sortable: false, 
            filterable: false,
            attributes:{style:"vertical-align:top;"},
            template: "<span>${id}</span>"
        },
        {
            'field':'reconcilie_time',
            'title':'Reconciliation Date', 
            width: 200, 
            sortable: true, 
            filterable: false, 
            attributes:{style:"vertical-align:top;"},
            template: '#= kendo.toString(kendo.parseDate(reconcilie_time), "yyyy/MM/dd HH:mm")#' 
        },
        {
            'field':'from_date',
            'title':'T-1 From', 
            width: 160, 
            sortable: false, 
            filterable: false, 
            attributes:{style:"vertical-align:top;"},
            template: '#= kendo.toString(kendo.parseDate(from_date), "yyyy/MM/dd")#'
        },
        {
            'field':'to_date',
            'title':'T-1 To', 
            width: 160, 
            sortable: false, 
            filterable: false, 
            attributes:{style:"vertical-align:top;"},
            template: '#= kendo.toString(kendo.parseDate(to_date), "yyyy/MM/dd")#'
        },
        {
            'field':'s_currency',
            'title':'P. Currency', 
            width: 100, 
            sortable: false, 
            filterable: false,
            encoded: false,
            attributes:{style:"vertical-align:top;"}

        },
        {
            'field':'s_amount',
            'title':'Amount', 
            width: 160, 
            sortable: false, 
            filterable: false, 
            encoded: false,
            attributes:{style:"vertical-align:top;text-align:right;"}
        },
        {
            'field':'s_count',
            'title':'Count', 
            width: 120, 
            sortable: false, 
            filterable: false, 
             encoded: false,
            attributes:{style:"vertical-align:top;text-align:right;"}
        },
        {
            'field':'s_processor_fee',
            'title':'Processor Fee', 
            width: 160, 
            sortable: false, 
            filterable: false, 
             encoded: false,
            attributes:{style:"vertical-align:top;text-align:right;"}
        },  
        {
            'field':'s_processor_net_amount',
            'title':'Net Amount',
             width: 160, 
            sortable: false, 
             filterable: false, 
             encoded: false,
             attributes:{style:"vertical-align:top;text-align:right;"}
         },
        {
            'field':'reconcilie_by',
            'title':'Reconcile by', 
            width: 120, 
            sortable: false, 
            filterable: false,
            attributes:{style:"vertical-align:top;"},
        },
        {
            field: "action",
            title: "Actions",
            width: 100,
            sortable: false,
            filterable: false,
            // template: "<a class='btn-view-tx' href=\"javascript:void(0);\" data-id='${id}'  data-type=\"reconciliation\">View</a>"
            attributes:{style:"vertical-align:top;"},
            template: "<a class='btn-view-tx' href=\"javascript:void(0);\" data-name='${name}' data-id='${id}' data-type=\"reconciliation\">View</a>"
        },
        {}
    ];

    // Grid of showing selected reconciliation
    $("#grid-reconciliation").kendoGrid({
        columns: reconciliationColumns,
        dataSource: reconciliationGridDs,
        selectable: "row",
        height: 400,
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
        },
    });
    var reconciliationGrid =  $('#grid-reconciliation').data('kendoGrid');




    var txGridViewFilter = null;

    /**
     * Error handler
     *
     * @param      {Object}  rst     The result
     */
    var errorHandler = function (rst)
    {
        if(rst.status == 'error' && rst.type){
            if(rst.type == 'TokenUsed'){
                alert('System busy, please try again in a few minutes');
            }
            if(rst.type == 'CannotCreateToken'){
                alert('System busy, please try again in a few minutes');
            }
            if(rst.type == 'InvalidSubmission'){
                alert('Settlement state has changed while you are editing. Please try again');
            }
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

    /**
     * Refresh the result of transaction grid
     */
    var refreshTransactionGrid = function()
    {
        var view = [];

        $('.filter-processor').text('All')
        $('.filter-merchant').text('All')
        if(txGridViewFilter != null){
            if(txGridViewFilter.type == 'acquirer'){
                transactionDs.filter( { field: "acquirer_mid", operator: "equal", currency: txGridViewFilter.currency, value: txGridViewFilter.value }) ;
                view = transactionDs.view();
                $('.filter-processor').text(txGridViewFilter.name)
            }
            if(txGridViewFilter.type == 'merchantgroup'){
                transactionDs.filter( { field: "merchantgroup_id", operator: "equal", currency: txGridViewFilter.currency, value: txGridViewFilter.value }) ;
                view = transactionDs.view();
                $('.filter-merchant').text(txGridViewFilter.name)
            }
        }
        $("#grid-transaction form.k-filter-menu button[type='reset']").trigger("click");
        
        transactionGridDs.data( view );
        transactionGridDs.page(1)
        
        // Reset the filterable checkbox list
        refreshDsForFields(transactionCustomerNameDs, 'customer_name', view);
        refreshDsForFields(transactionEmailDs, 'email', view);
        refreshDsForFields(transactionProcessorNameDs, 'processor_name', view);
        refreshDsForFields(transactionMerchantDs, 'merchant', view);
        refreshDsForFields(transactionMerchantGroupDs, 'merchantgroup_name', view);
        refreshDsForFields(transactionStateDs, 'state', view);
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
        if(data.type == 'reconciliation'){
            searchInfo.reconciliation_batch_id = data.id;
            showIndicator()
            queryTx();
        }
    })


    // Handle data search form
    $('.users.form form').on('submit', function(evt){
        evt.preventDefault();

        showIndicator()
        queryBatch();
        // dataSource.read();
    })

    // Handle download button action 
    $('.users.form .download-btn').on('click', function(){
        $.ajax({
            url:'<?=$this->Url->build([ "action" => "download" ])?>',
            data: {
                start_date: searchInfo.reconiliation_from_date,
                end_date: searchInfo.reconiliation_to_date,
                txid: searchInfo.txids.join(','),
            },
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
        hideIndicator()

        dataSource.data(null)
        merchantDs.data(null)
        transactionDs.data(null)
        acquirerDs.data(null)
        searchInfo.txids.length = 0;
        reloadTx();
    }

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
</style>