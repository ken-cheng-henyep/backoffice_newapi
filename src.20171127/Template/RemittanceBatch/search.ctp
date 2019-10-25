<div class="remittanceBatch index large-12 medium-10 columns content">
    <h3><?= __('Batch Search') ?></h3>
    <?= $this->element('search_form_grid') ?>

    <div>&nbsp</div>
    <div id="grid"></div>
    <script>
        $(document).ready(function(){
            var dataSource = new kendo.data.DataSource({
                serverFiltering: true,
                transport: {
                    read: "json",
                    dataType: "json",
                            /*function (e) {
                        // on success
                        e.success(sampleData);
                        // on failure
                        e.error("XHR response", "status code", "error message");
                    },
                    */

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
                            upload_time: { type: "date" },
                            complete_time: { type: "date" }
                        }
                    }
                },
                pageSize: 10,
                sortable: true,
                serverPaging: true,
                serverSorting: true,
                sort: { field: "upload_time", dir: "desc" }
            });

            $("#grid").kendoGrid({
                columns: [{field: "id",title: "Id", width: 200, attributes:{id:"row-batch-id"}},
                    {field: "merchants_name",title: "Merchant", width: 120},
                    {field: "upload_time",title: "Upload Time", format: "{0:yyyy-MM-dd HH:mm}", width: 200},
                    {field: "count",title: "Count", width: 80, attributes:{class:"numeric"}},
                    {field: "non_cny",title: "Currency", width: 60},
                    //{field: "total_usd",title: "Total USD", format: "{0:n2}", width: 150, attributes:{class:"numeric"}},
                    //{field: "total_cny",title: "Total CNY", format: "{0:n2}", width: 150, attributes:{class:"numeric"}},
                    {field: "total_usd",title: "Converted Amount", format: "{0:n2}", width: 150, attributes:{class:"numeric"}},
                    {field: "total_cny",title: "CNY Amount", format: "{0:n2}", width: 150, attributes:{class:"numeric"}},
                    //{field: "complete_convert_rate",title: "Rate", format: "{0:n4}", width: 80, attributes:{class:"numeric"}},
                    //{field: "status_name",title: "Status", width: 150, sortable: false},
                    {field: "status_text",title: "Status", width: 150, sortable: true},
                    {field: "target_name",title: "Channel", width: 80, sortable: true},
                    {field: "action", title: "Actions", width: 100, sortable: false, template:"<a href='${action_url}'>${action}</a>" }
                ],
                dataSource: dataSource,
                height: 400,
                /*change: onGridChange, */
                selectable: "row",
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