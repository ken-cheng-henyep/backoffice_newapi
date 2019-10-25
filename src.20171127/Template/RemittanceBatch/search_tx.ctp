<div class="remittanceBatch index large-12 columns">
    <h3 class=""><?= __('Batch Transaction Search') ?></h3>
    <div class="batch-detail">
            <?= $this->element('search_tx_form') ?>

            <div>&nbsp</div>
            <div id="grid"></div>
            <script>
                $(document).ready(function(){
                    var dataSource = new kendo.data.DataSource({
                        serverFiltering: true,
                        transport: {
                            read: "tx-json",
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
                        pageSize: 10,
                        sortable: true,
                        serverPaging: true,
                        serverSorting: true,
                        filter: [
                            {field: "start", operator: "eq", value: $("#start").val()},
                            {field: "end", operator: "lte", value: $("#end").val()}
                        ],
                        sort: { field: "create_time", dir: "desc" }
                    });

                    $("#grid").kendoGrid({
                        columns: [
                            //{field: "batch_id",title: "Id", width: 50, attributes:{id:"row-batch-id"}},
                            {field: "create_time",title: "Tx Time", format: "{0:yyyy-MM-dd HH:mm}", width: 120},
                            {field: "merchant_name",title: "Merchant", width: 100},
                            {field: "batch_id",title: "Batch ID", width: 120},
                            {field: "batch_status_text",title: "Batch Status", width: 120},
                            {field: "tx_status_name",title: "Tx Status", width: 80},
                            {field: "beneficiary_name",title: "Name", width: 60},
                            {field: "account",title: "Account No.", width: 200, attributes:{}},
                            {field: "bank_name",title: "Bank Name", width: 120},
                            {field: "bank_branch",title: "Bank Branch", width: 150},
                            {field: "province",title: "Province", width: 80},
                            {field: "city",title: "City", width: 80},
                            {field: "id_number",title: "ID Card", width: 180},
                            {field: "amount",title: "CNY", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                            {field: "convert_currency",title: "Currency", width: 60},
                            {field: "convert_amount",title: "Converted Amount", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                            {field: "convert_rate",title: "Rate", format: "{0:n4}", width: 80, attributes:{class:"numeric"}},
                            {field: "merchant_ref",title: "Reference", width: 120},
                            {field: "blocked",title: "Remarks", width: 120},
                            {field: "flagged",title: "Flagged", width: 120},
                            {field: "action", title: "Actions", width: 100, sortable: false, encoded: false
                                , template: "<a href='${action_url}'>View</a>"
                            }
                        ],
                        dataSource: dataSource,
                        height: 400,
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
                    });
                });

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
                <?php use Cake\Routing\Router;
                    ?>
                    var url = "<?=Router::url(['action' => 'view']) ?>" +"/"+batchid;
                    console.log('onGridChange URL:', url);
                    window.location.href = url;
                }


            </script>

    </div>
</div>