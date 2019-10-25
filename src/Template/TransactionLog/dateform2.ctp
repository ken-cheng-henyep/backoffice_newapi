<!-- File: src/Template/Pages/dateform.ctp -->
<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <h3>Transaction Search</h3>
    <?= $this->Form->create(null, ['url' => ['controller' => 'TransactionLog', 'action' => 'dateform']]) ?>
    <fieldset>
        <legend><?= __('Select Date Range') ?></legend>
        <?= $this->Form->input('startdate', ['type'=>'text', 'label' => 'Start Date', 'id'=>'startdt', 'required' => true]) ?>
        <?= $this->Form->input('enddate', ['type'=>'text', 'label' => 'End Date', 'id'=>'enddt', 'required' => true]) ?>
    </fieldset>
    <div id="buttonContainer">
        <?= $this->Form->button(__('Search'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'onclick'=>'updateGrid()']); ?>
        <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel"]); ?>
        <?= $this->Form->button(__('Download'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"", 'onclick'=>'getReport()']); ?>
        <?='' /* = $this->Form->button(__('Download'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"download", 'data-sprite-css-class'=>"k-button k-icon k-i-download", 'onclick'=>'getReport()']); */?>
    </div>
    <?= $this->Form->end() ?>
</div>
<div>Summary</div>
<div id="sum-grid"></div>
<div><hr/></div>
<div>Details</div>
<div id="detail-grid"></div>
<script>
$(document).ready(function() {
    function startChange() {
        var startDate = start.value(),
                endDate = end.value();

        if (startDate) {
            startDate = new Date(startDate);
            startDate.setDate(startDate.getDate());
            end.min(startDate);
        } else if (endDate) {
            start.max(new Date(endDate));
        } else {
            endDate = new Date();
            start.max(endDate);
            end.min(endDate);
        }
    }

    function endChange() {
        var endDate = end.value(),
                startDate = start.value();

        if (endDate) {
            endDate = new Date(endDate);
            endDate.setDate(endDate.getDate());
            start.max(endDate);
        } else if (startDate) {
            end.min(new Date(startDate));
        } else {
            endDate = new Date();
            start.max(endDate);
            end.min(endDate);
        }
    }

    var today = new Date();
    var yesterday = new Date(today);
    // T-2
    yesterday.setDate(today.getDate() - 2);

    var start = $("#startdt").kendoDatePicker({
        value: yesterday,
        format: "yyyy/MM/dd",
        change: startChange
    }).data("kendoDatePicker");

    var end = $("#enddt").kendoDatePicker({
        value: yesterday,
        format: "yyyy/MM/dd",
        change: endChange
    }).data("kendoDatePicker");

    start.max(end.value());
    end.min(start.value());

    kendo.init("#buttonContainer");

    var dataSource = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: "transactionJson",
            dataType: "json",
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                fields: {
                    state_time: { type: "date" },
                    transaction_time: { type: "date" },
                    amount: { type: "number" },
                    convert_amount: { type: "number" },
                    convert_rate: { type: "number" },
                    wecollect_fee: { type: "number" },
                    processor_fee: { type: "number" },
                }
            }
        },
        pageSize: 10,
        sortable: true,
        serverPaging: true,
        serverSorting: true,
        sort: { field: "state_time", dir: "desc" }
    });

    var sumDS = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: "transactionJson/summary",
            dataType: "json",
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                fields: {
                    amount_ttl: { type: "number" },
                    convert_ttl: { type: "number" },
                    avg_rate: { type: "number" },
                    wecollect_fee_ttl: { type: "number" },
                    processor_fee_ttl: { type: "number" },
                }
            }
        },
        pageSize: 50,
        sortable: true,
        serverPaging: true,
        serverSorting: false,
        sort: { field: "merchant", dir: "asc" }
    });

    $("#detail-grid").kendoGrid({
        columns: [{field: "state_time",title: "State Time", format: "{0:yyyy-MM-dd HH:mm}", width: 120},
            {field: "state",title: "State", width: 80},
            {field: "transaction_time",title: "Transaction Time", format: "{0:yyyy-MM-dd HH:mm}", width: 120},
            //    {field: "transaction_state",title: "Transaction State"},
            {field: "customer",title: "Name", width: 80},
            {field: "email",title: "Email", width: 120},
            {field: "merchant",title: "Merchant", width: 200},
            {field: "amount",title: "CNY Amount", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
            {field: "convert_rate",title: "Rate", format: "{0:n4}", width: 80, attributes:{class:"numeric"}},
            {field: "convert_currency",title: "Currency", width: 40},
            {field: "convert_amount",title: "Converted", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
            {field: "fx_package",title: "Package", width: 40, attributes:{class:"numeric"}},
            //{field: "status_name",title: "Status", width: 150, sortable: false},
            {field: "acquirer",title: "Acquirer", width: 80, sortable: true},
            {field: "internal_id",title: "Internal Id", width: 50, sortable: true},
            {field: "processor_account_no",title: "Processor Account", width: 200},
            {field: "wecollect_fee",title: "WeCollect Fee", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
            {field: "processor_fee",title: "Processor Fee", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
        ],
        dataSource: dataSource,
        height: 500,
        toolbar: false,
        /*change: onGridChange, */
        selectable: "row",
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        pageable: {
            refresh: true,
            pageSizes: [10, 50, 100, "all"],
            buttonCount: 2
        },
        resizable: true,
        filterable: false,
    });

    $("#sum-grid").kendoGrid({
        columns: [{field: "merchant",title: "Merchant", width: 200},
            {field: "merchant_id",title: "Merchant ID", width: 160},
            {field: "count",title: "No of Transaction", format: "{0:n0}", width: 80, attributes:{class:"numeric"}},
            {field: "amount_ttl",title: "Total Amount", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
            {field: "currency",title: "Currency", width: 40},
            {field: "convert_ttl",title: "Converted", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
            {field: "avg_rate",title: "Average Rate", format: "{0:n4}", width: 80, attributes:{class:"numeric"}},
            {field: "wecollect_fee_ttl",title: "WeCollect Fee", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
            {field: "processor_fee_ttl",title: "Processor Fee", format: "{0:n2}", width: 80, attributes:{class:"numeric"}},
        ],
        dataSource: sumDS,
        height: 300,
        toolbar: false,
        /*change: onGridChange, */
        selectable: "row",
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        pageable: {
            refresh: true,
        },
        resizable: true,
        filterable: false,
    });
});

function updateGrid() {
    //update grid
    $('#sum-grid').data("kendoGrid").dataSource.filter( [{ field: "start", operator: "eq", value: $("#startdt").val()},
        {field: "end", operator: "lte", value: $("#enddt").val()},
    ]);
    $('#detail-grid').data("kendoGrid").dataSource.filter( [{ field: "start", operator: "eq", value: $("#startdt").val()},
        {field: "end", operator: "lte", value: $("#enddt").val()},
    ]);
    $('#detail-grid').data("kendoGrid").dataSource.filter.logic = 'and';
}

function getReport() {
    //updateGrid();
    //$.post( "<?=$this->Url->build(["controller" => "TransactionLog","action" => "dateform2"])?>", { startdate: $("#startdt").val(), enddate: $("#enddt").val() } );
    window.location.href = "<?=$this->Url->build(["controller" => "TransactionLog","action" => "dateform2"])?>"+"?"+ $.param({ startdate: $("#startdt").val(), enddate: $("#enddt").val() });
    updateGrid();
}
</script>