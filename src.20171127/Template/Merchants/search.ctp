<?php
        use Cake\Routing\Router;
        ?>
<div class="remittanceBatch index large-12 columns">
    <h3 class=""><?= __('Merchant Account Search') ?></h3>
    <div class="batch-detail">
        <?= $this->element('search_merchant_form') ?>

        <div>&nbsp</div>
        <div id="grid"></div>
        <div>&nbsp</div>
        <script>
            $(document).ready(function(){
                var dataSource = new kendo.data.DataSource({
                    serverFiltering: true,
                    transport: {
                        //read: "instant-json",
                        read: "<?=$json_url?>",
                        dataType: "json",
                    },
                    change: function(e) {
                        var data = this.data();
                        onDsChange(data.length);
                    },
                    schema: {
                        data: "data",
                        total: "total",
                        model: {
                            fields: {
                                create_time: { type: "date" }
                            }
                        }
                    },
                    pageSize: 20,
                    sortable: false,
                    serverPaging: true,
                    serverSorting: true,
                    filter: [
                        //{field: "master_merchant", operator: "eq", value: $("#dd-merchant").val()},
                        //{field: "end", operator: "lte", value: $("#end").val()},
                        //{field: "txid", operator: "eq", value: $("#txid").val()},
                    ],
                    sort: { field: "create_time", dir: "desc" }
                });

                $("#grid").kendoGrid({
                    columns: [
                        //{field: "create_time",title: "Tx Time", format: "{0:yyyy-MM-dd HH:mm}", width: 120},
                        {field: "group_name",title: "Master Merchant", width: 100},
                        {field: "name",title: "Name", width: 200},
                        {field: "id",title: "Merchant ID", width: 120, attributes:{}},
                        {field: "enabled",title: "Enabled", width: 60},
                        {field: "settle_fee",title: "MDR Fee in %", format: "{0:n2}", width: 60, attributes:{class:"numeric"}},
                        {field: "settle_min_fee_cny",title: "MDR Min Fee", format: "{0:n2}", width: 60, attributes:{class:"numeric"}},
                        {field: "refund_fee_cny",title: "Refund Fee", format: "{0:n2}", width: 60, attributes:{class:"numeric"}},
                        {field: "settle_option",title: "FX Package", width: 60},
                        {field: "settle_rate_symbol",title: "FX Rate Symbol", width: 60},
                        {field: "round_precision",title: "Rounding Precision", width: 60},
                        {field: "processor_settle_currency",title: "Processor Settlement Currency", width: 60},
                        {field: "fx_source",title: "FX Source", width: 60},
                        {field: "settle_currency",title: "Settlement Currency", width: 60},
                        {field: "settle_handling_fee",title: "Settlement Handling Fee", format: "{0:n2}", width: 60, attributes:{class:"numeric"}},
                        {field: "remittance_fee",title: "Cross Border Fee %", format: "{0:n2}", width: 60, attributes:{class:"numeric"}},
                        {field: "remittance_min_fee",title: "Cross Border Min Fee", format: "{0:n2}", width: 60, attributes:{class:"numeric"}},
                        {field: "remittance_fee_type",title: "Fee Bearer", width: 60},
                        {field: "local_remittance_enabled",title: "Local Remittance Enabled", width: 60},
                        {field: "local_remittance_fee",title: "Local Remittance Fee (CNY)", format: "{0:n2}", width: 60, attributes:{class:"numeric"}},
                        {field: "remittance_preauthorized",title: "Pre-authorized Enabled", width: 60},
                        {field: "remittance_api_enabled",title: "Remittance API Enabled", width: 60},
                        {field: "skip_balance_check",title: "Skip Balance Check", width: 60},
                        {field: "remittance_netting",title: "Remittance Netting", width: 60},
                        {field: "action", title: "Action", width: 60, sortable: false, encoded: false
                            , template: "<a href='${action_url}'>Edit</a>"
                        }
                    ],
                    dataSource: dataSource,
                    height: 600,
                    /*change: onGridChange, */
                    selectable: "row",
                    allowCopy: true,
                    resizable: true,
                    sortable: {
                        mode: "single",
                        allowUnsort: true
                    },
                    pageable: {
                        refresh: true,
                        //pageSizes: true,
                        pageSizes: [10, 20, 50, "All"],
                        buttonCount: 2
                    },
                    filterable: false,
                    dataBound: function(e) {
                        console.log("grid dataBound");
                        //first 3 col no wrapping
                        this.autoFitColumn("group_name");
                        this.autoFitColumn("name");
                        this.autoFitColumn("id");
                    }
                });


            });//ready


            function onDsChange(cnt) {
                console.log("onDsChange="+cnt);
                if (cnt>0)
                    $("#dl-excel").show();
                else
                    $("#dl-excel").hide();
            }

            function onGridChange(arg) {
                var batchid = this.select().find("#row-batch-id").text();
                /*
                 var selected = $.map(this.select(), function (item) {
                 console.log('selected:',item);

                 return $(item).text();
                 });
                 */
                var url = "<?=Router::url(['action' => 'view']) ?>" +"/"+batchid;
                console.log('onGridChange URL:', url);
                window.location.href = url;
            }


        </script>

    </div>
</div>