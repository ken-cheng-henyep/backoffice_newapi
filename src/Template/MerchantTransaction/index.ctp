<!--
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.4.0/jszip.min.js"></script>
-->
<?= $this->Html->script('jquery.i18Now.1.4.1.js',['async'=>false]) ?>
<div class="remittanceBatch index large-12 medium-10 columns content">
    <h3><?= __('Merchant Balance') ?></h3>
    <div id="time">
        Balance as of: <span id="time-text"><?=date('Y-m-d H:i:s')?></span>
    </div>
    <div id="grid"></div>
    <script>
        $(document).ready(function(){
            var dataSource = new kendo.data.DataSource({
                serverFiltering: true,
                transport: {
                    read: "json",
                    dataType: "json",
                    /*
                    function (e) {
                     // on success
                     e.success(sampleData);
                     // on failure
                     e.error("XHR response", "status code", "error message");
                     },
                     */
                },
                schema: {
                    data: "data",
                    total: "total",
                    model: {
                        fields: {
                            upload_time: { type: "date" }
                        }
                    }
                },
                change: function(e) {
                      //var data = this.data();
                    var d = new Date();
                    console.log('ds change');
                    //console.log($.now());
                    //$("#time-text").text(d);
                    $("#time-text").i18Now({format : "%Y-%m-%d %H:%i:%s"});
                },
                pageSize: 20,
                sortable: true,
                serverPaging: false,
                serverSorting: false,
                sort: { field: "merchants_name", dir: "asc" }
            });

            $("#grid").kendoGrid({
                toolbar: ["excel"],
                excel: {
                    allPages: true,
                    fileName: "MerchantBalance.xlsx"
                },
                excelExport: function(e) {
                    // Prevent the default behavior which will prompt the user to save the generated file.
                    e.preventDefault();
                    //console.log('excelExport');
                    window.location.href = "<?=$dl_url?>";
                },
                columns: [{field: "merchant_id",title: "Id", width: 120, attributes:{id:"row-id"}},
                    {field: "merchant_name",title: "Merchant", width: 300},
                    {field: "wallet_name",title: "Wallet", width: 200},
                    {field: "currency",title: "Currency", width: 80},
                    {field: "balance",title: "Balance", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                    {field: "service_name",title: "Service", width: 80},
                    //{field: "account_status",title: "Status", width: 40, attributes:{class:"numeric"}},
                    {field: "action", title: "Actions", width: 100, sortable: false, template:"<a href='${action_url}'>View</a>" }
                ],
                dataSource: dataSource,
                height: 600,
                /*change: onGridChange, */
                selectable: "row",
                sortable: {
                    mode: "single",
                    allowUnsort: true
                },
                pageable: {
                    refresh: true,
                    pageSizes: [20, 40, 100, "all"],
                    /*
                    pageSizes: true,
                    buttonCount: 2
                    */
                },
                filterable: false,
                resizable: true,
            });
        });

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

    <div>&nbsp;</div>
</div>