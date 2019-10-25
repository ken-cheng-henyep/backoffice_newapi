<?php
        use Cake\Routing\Router;
        ?>
<div class="remittanceBatch index large-12 columns">
    <h3 class=""><?= __('Instant Transaction Search') ?></h3>
    <div class="batch-detail">
        <?= $this->element('search_instant_form') ?>

        <div>&nbsp</div>
        <div id="grid"></div>
        <div>&nbsp</div>
        <script>
            $(document).ready(function(){
                var dataSource = new kendo.data.DataSource({
                    serverFiltering: true,
                    transport: {
                        //read: "instant-json",
                        read: "<?=Router::url(['action' => 'instantJson']) ?>",
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
                        {field: "end", operator: "lte", value: $("#end").val()},
                        {field: "txid", operator: "eq", value: $("#txid").val()},
                    ],
                    sort: { field: "create_time", dir: "desc" }
                });

                $("#grid").kendoGrid({
                    columns: [
                        {field: "create_time",title: "Tx Time", format: "{0:yyyy-MM-dd HH:mm}", width: 120},
                        {field: "merchant.name",title: "Merchant", width: 100},
                        {field: "name",title: "Name", width: 60},
                        {field: "account",title: "Account No.", width: 200, attributes:{}},
                        {field: "bank_name",title: "Bank Name", width: 120},
                        {field: "bank_branch",title: "Bank Branch", width: 150},
                        {field: "province",title: "Province", width: 80},
                        {field: "city",title: "City", width: 80},
                        {field: "id_number",title: "ID Card", width: 180},

                        {field: "amount",title: "CNY", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                        {field: "gross_amount_cny",title: "Client received", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                        {field: "gross_amount_cny",title: "Gross Amount", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                        {field: "fee_cny",title: "Fee", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                        {field: "paid_amount",title: "Amount paid", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},

                        {field: "convert_currency",title: "Currency", width: 60},
                        {field: "convert_amount",title: "Converted Amount", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                        {field: "convert_rate",title: "Rate", format: "{0:n4}", width: 80, attributes:{class:"numeric"}},
                        {field: "merchant_ref",title: "Reference", width: 180},
                        {field: "id_type",title: "ID Card Type", width: 40},
                        {field: "status_name",title: "Status", width: 80, sortable: false},
                        {field: "id",title: "Trans ID", width: 240},
                        {field: "target_name",title: "Processor", width: 120},
                        {field: "remarks",title: "Remarks", width: 100},
                        {field: "flagged",title: "Flagged", width: 100},
                        {field: "action", title: "Actions", width: 100, sortable: false, encoded: false
                            /*, template: "<a href='${action_url}'>View</a>"*/
                            , template: function(item) {
                            //console.log(item);
                                var output = '';
                                if (item.action_text != null)
                                    output +="<a class='' href=\"javascript:void(0);\" onclick=\"setStatus('" + item.id + "','" + item.next_status + "','"+ item.action_text +"');\">" + item.action_text + "</a>";
                                if (item.action_text2 != null)
                                    output +="<br/><a class='' href=\"javascript:void(0);\" onclick=\"setStatus('" + item.id + "','" + item.next_status2 + "','"+ item.action_text2 +"');\">" + item.action_text2 + "</a>";
                                return output;
                            }
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

            function setStatus(id,status,text) {
                console.log(id+ " ,setStatus="+status+" text:"+text);

                var url= "<?=$update_url?>";

                if (confirm("Are you sure you want to set ("+id+") to "+text+" ?")) {
                    $.post(url, 'id=' + id + '&status=' + status, function (data) {
                        console.log("return:" + data.status);
                        if (data.status == 1) {
                            //show updated total
                            //if (status=='fail')
                            //location.reload();
                            //$('#grid').data('kendoGrid').dataSource.read();
                            updateGrid();
                            //$('#GridName').data('kendoGrid').refresh();
                            return true;
                        }
                    }, 'json');
                }
                console.log('end post');
                return false;
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