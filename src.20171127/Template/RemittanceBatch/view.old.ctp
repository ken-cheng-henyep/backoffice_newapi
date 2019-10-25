<!--
<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><? //$this->Html->link(__('Edit Remittance Batch'), ['action' => 'edit', $remittanceBatch->id]) ?> </li>
        <li><? //$this->Form->postLink(__('Delete Remittance Batch'), ['action' => 'delete', $remittanceBatch->id], ['confirm' => __('Are you sure you want to delete # {0}?', $remittanceBatch->id)]) ?> </li>
        <li><?= $this->Html->link(__('List Remittance Batch'), ['action' => 'index']) ?> </li>
        <li><?= $this->Html->link(__('New Remittance Batch'), ['action' => 'add']) ?> </li>
        <li><?= $this->Html->link(__('List Merchants'), ['controller' => 'Merchants', 'action' => 'index']) ?> </li>
        <li><?= $this->Html->link(__('New Merchant'), ['controller' => 'Merchants', 'action' => 'add']) ?> </li>
    </ul>
</nav>
-->

<div class="remittanceBatch view large-11 medium-10 columns content">
    <?= $this->Form->create(null, ['url' => ['controller' => 'RemittanceBatch', 'action' => 'view', 'id'=>$remittanceBatch[0]['batch_id']]]) ?>
    <table class="vertical-table">
        <tr>
            <th><?= __('Batch Id') ?></th>
            <td id="batch_id"><?= h($remittanceBatch[0]['batch_id']) ?></td>
        </tr>
        <tr>
            <th><?= __('Merchant') ?></th>
            <td><?= h($remittanceBatch[0]['merchant_name']) ?></td>
            <!--
            <td><?/* = $remittanceBatch->has('merchant') ? $this->Html->link($remittanceBatch->merchant->name, ['controller' => 'Merchants', 'action' => 'view', $remittanceBatch->merchant->id]) : '' */?></td>
            -->
        </tr>
        <tr>
            <th><?= __('Upload Time') ?></th>
            <td><?= h($remittanceBatch[0]['upload_time']) ?></td>
        </tr>
        <tr>
            <th><?= __('Upload Username') ?></th>
            <td><?= h($remittanceBatch[0]['username']) ?></td>
        </tr>
        <tr>
            <th><?= __('Status') ?></th>
            <td><?= h($remittanceBatch[0]['status_name']) ?></td>
        </tr>
        <tr>
            <th><?= __('Count') ?></th>
            <td><?= $this->Number->format($remittanceBatch[0]['count']) ?></td>
        </tr>
        <tr>
            <th><?= __('Total amount USD') ?></th>
            <td><?= $this->Number->precision($remittanceBatch[0]['total_usd'],2) ?></td>
        </tr>
        <tr>
            <th><?= __('Total amount CNY') ?></th>
            <td><?= $this->Number->precision($remittanceBatch[0]['total_cny'],2) ?></td>
        </tr>
        <tr>
            <th><?= __('Quoted Rate') ?></th>
            <td><?= $this->Number->precision($remittanceBatch[0]['total_convert_rate'],4) ?></td>
        </tr>
        <tr>
            <th><?= __('Approved Rate') ?></th>
            <td><input id="quote-rate"></td>
            <?='';/* $this->Form->input('quote_rate',['id'=>'quote-rate', 'min'=>"1", 'max'=>"99", 'type' => 'text','label'=>false,'class'=>'k-textbox', 'value'=>(isset($remittanceBatch[0]['quote_convert_rate'])?$remittanceBatch[0]['quote_convert_rate']:$remittanceBatch[0]['total_convert_rate']) ] ) */?>
        </tr>
        <tr>
            <th><?= __('Completed Rate') ?></th>
            <td><input id="complete-rate"></td>
            <?='';/* $this->Form->input('complete_rate',['id'=>'complete-rate', 'type' => 'text','label'=>false,'class'=>'k-textbox', 'value'=> (isset($remittanceBatch[0]['complete_convert_rate'])?$remittanceBatch[0]['complete_convert_rate']:$remittanceBatch[0]['quote_convert_rate']) ] ) */?>
        </tr>
        <tr>
            <th><?= __('Approved Time') ?></th>
            <td><?= h($remittanceBatch[0]['approve_time']) ?></td>
        </tr>
        <tr>
            <th><?= __('Completed Time') ?></th>
            <td><?= h($remittanceBatch[0]['complete_time']) ?></td>
        </tr>
        <tr>
            <th><?= __('Channel') ?></th>
            <td><input id="target"></td>
        </tr>

    </table>

    <fieldset>
        <?= '';/* $this->Form->select('target',
        ['1'=>'Payment Asia Excel', '2'=>'ChinaGPay Excel', ],
        ['empty' => '(choose one)', 'required' => false, 'id'=>'target', 'value'=>$remittanceBatch[0]['target'] ]); */?>
    </fieldset>
    <?= $this->Form->hidden('status_name', ['id'=>'status_name', 'value'=>$remittanceBatch[0]['status_name'] ]); ?>
    <?= $this->Form->hidden('log_ready', ['id'=>'log_ready', 'value'=>$remittanceBatch[0]['all_log_set'] ]); ?>

    <?= $this->Form->button('Approve', ['type' => 'button', 'id'=>'ap_button']); ?>
    <?= $this->Form->button('Excel Download', ['type' => 'button', 'id'=>'ex_button']); ?>
    <?= $this->Form->button('Report Download', ['type' => 'button', 'id'=>'rp_button', 'hidden'=>true]); ?>
    <?= $this->Form->button('Decline', ['type' => 'button', 'id'=>'de_button']); ?>
    <?= $this->Form->button('Complete', ['type' => 'button', 'id'=>'cp_button', 'hidden'=>true]); ?>
    <script>
        $(document).ready(function() {
            $('#quote-rate').kendoNumericTextBox({
                value: 0,
                min: 0,
                max: 999,
                step: 0.0001,
                format: "n4",
                decimals: 4,
                change: onTBChange,
                spin: onTBChange
            });
            $('#complete-rate').kendoNumericTextBox({
                value: 0,
                min: 0,
                max: 999,
                step: 0.0001,
                format: "n4",
                decimals: 4,
                change: onTBChange,
                spin: onTBChange
            });
            $("#target").kendoDropDownList({
                value: "<?=$remittanceBatch[0]['target']?>",
                dataTextField: "text",
                dataValueField: "value",
                dataSource: [
                    { text: "(choose one)", value: "" },
                    { text: "Payment Asia Excel", value: "1" },
                    { text: "ChinaGPay Excel", value: "2" }
                ],
                change: onTBChange
            });

            var status = $('#status_name').val().toLowerCase();
            var log_ready = $('#log_ready').val();
            console.log("status:"+status+" log_ready:"+log_ready);
            if (status=='processing') {
                //$('#quote-rate').prop('readonly', true);
                $("#quote-rate").data("kendoNumericTextBox").value("<?=$remittanceBatch[0]['quote_convert_rate']?>");
                $("#quote-rate").data("kendoNumericTextBox").readonly(true);
                //$('#complete-rate').prop('readonly', false);
                $("#complete-rate").data("kendoNumericTextBox").readonly(false);
                $("#complete-rate").data("kendoNumericTextBox").wrapper.show();

                //$('#target').attr('disabled', true);
                $("#target").data("kendoDropDownList").enable(false);
                $('#ap_button').hide();
                //check target
                $('#ex_button').attr('disabled', false);
                $('#de_button').hide();
                $('.actions').show();
                //if (log_ready)
                $('#cp_button').show();
                $('#cp_button').attr('disabled', (!readyToComplete()));
                $('#rp_button').show();
            } else if (status=='queued') {
                //$('#quote-rate').prop('readonly', false);
                $("#quote-rate").data("kendoNumericTextBox").readonly(false);
                //$('#complete-rate').hide();
                $("#complete-rate").data("kendoNumericTextBox").wrapper.hide();
                //$('#target').attr('disabled', false);
                $("#target").data("kendoDropDownList").enable(true);
                //$('#ap_button').show();
                $('#ap_button').attr('disabled', true);
                $('#ex_button').hide();//
                $('#de_button').show();
                $('.actions').hide();
            } else if (status=='declined') {
                //$('#quote-rate').prop('readonly', true);
                $("#quote-rate").data("kendoNumericTextBox").wrapper.hide();
                //$('#complete-rate').prop('readonly', true);
                $("#complete-rate").data("kendoNumericTextBox").wrapper.hide();
                //$('#target').attr('disabled', true);
                $("#target").data("kendoDropDownList").enable(false);
                $('#ap_button').hide();
                $('#ex_button').hide();//attr('disabled', false);
                $('#de_button').hide();
                $('.actions').hide();
            } else if (status=='completed') {
                //$('#quote-rate').prop('readonly', true);
                //$('#complete-rate').prop('readonly', true);
                $("#quote-rate").data("kendoNumericTextBox").value("<?=$remittanceBatch[0]['quote_convert_rate']?>");
                $("#quote-rate").data("kendoNumericTextBox").readonly(true);
                //$('#complete-rate').prop('readonly', false);
                $("#complete-rate").data("kendoNumericTextBox").value("<?=$remittanceBatch[0]['complete_convert_rate']?>");
                $("#complete-rate").data("kendoNumericTextBox").readonly(true);
                //$('#target').attr('disabled', true);
                $("#target").data("kendoDropDownList").enable(false);
                $('#ap_button').hide();
                $('#ex_button').hide();//attr('disabled', false);
                $('#de_button').hide();
                $('#rp_button').show();
                $('.actions').hide();
            } else {  // no status
                //$('#quote-rate').prop('readonly', true);
                //$('#complete-rate').prop('readonly', true);
                //$('#target').attr('disabled', true);
                $("#quote-rate").data("kendoNumericTextBox").wrapper.hide();
                $("#complete-rate").data("kendoNumericTextBox").wrapper.hide();
                $("#target").data("kendoDropDownList").enable(false);
                $('#ap_button').hide();
                $('#ex_button').hide();
                $('#de_button').hide();
                $('.actions').hide();
            }

            $('#ap_button').click(function(event) {
                var target = $("#target").val();
                console.log("target:"+target);
                console.log("q_rate:"+$("#quote-rate").val());

                if (target=='') {
                    alert("Please select a Channel.");
                } else {
                    if (confirm("Are you sure you want to Approve this batch?"))
                        updateStatus("processing");
                }
            });

            $('#de_button').click(function(event) {
                if (confirm("Are you sure you want to Decline this batch?"))
                    updateStatus("declined");
            });
            $('#cp_button').click(function(event) {
                if (confirm("Are you sure you want to Complete this batch?"))
                    updateStatus("completed");
            });

            $('#ex_button').click(function(event) {
                var id="<?=$remittanceBatch[0]['batch_id']?>";
                var url='<?= $this->Url->build(["controller" => "RemittanceBatch", "action" => "downloadExcel",])?>';
                var target = $("#target").val();
                url = url + '?batch_id='+id+'&target='+target;
                window.open(url);

                return false;
                //$(location).attr('href', url);
            });
            $('#rp_button').click(function(event) {
                var id="<?=$remittanceBatch[0]['batch_id']?>";
                var url='<?= $this->Url->build(["controller" => "RemittanceBatch", "action" => "downloadReport",])?>';
                url = url + '?batch_id='+id +'&status='+ status;
                window.open(url);
                return false;
            });

            $('.act-ok').click(function(event) {
                event.preventDefault();
                //updateStatus("declined");
                console.log($(this).attr('data-id'));
                updateLogStatus($(this).attr('data-bid'),$(this).attr('data-id'),'ok');
            });
            $('.act-fail').click(function(event) {
                event.preventDefault();
                console.log($(this).attr('data-id'));
                updateLogStatus($(this).attr('data-bid'),$(this).attr('data-id'),'fail');
            });
            $('.act-all-ok').click(function(event) {
                event.preventDefault();
                console.log($(this).attr('data-bid'));
                updateLogStatus($(this).attr('data-bid'),'all','ok');
            });

        });
        /*
         $("#ex_button").kendoButton({
         //enable: false
         });
         */
        function updateStatus(status) {
            var id="<?=$remittanceBatch[0]['batch_id']?>";
            var url='<?= $this->Url->build(["controller" => "RemittanceBatch", "action" => "updateStatus",])?>';
            var target = $("#target").val();
            var q_rate = $("#quote-rate").val();
            var c_rate = $("#complete-rate").val();

            console.log("id:"+id+" s:"+status+" url:"+url);

            $.post(url,'batch_id='+id+'&status='+status+'&target='+target+'&q_rate='+q_rate+'&c_rate='+c_rate, function(data) {
                console.log("return:"+data.status);
                if (data.status==0)
                    location.reload();
            }, 'json');

            console.log('end post');
            //location.reload();
        }
        function updateLogStatus(bid,id,status) {
            var url='<?= $this->Url->build(["controller" => "RemittanceBatch", "action" => "updateLogStatus",])?>';
            console.log("id:"+id+" s:"+status+" url:"+url);

            $.post(url,'batch_id='+bid+'&id='+id+'&status='+status, function(data) {
                console.log("return:"+data.status);
                if (data.status==0) {
                    //show updated total
                    //if (status=='fail')
                    location.reload();

                    return true;
                }
            }, 'json');

            console.log('end post');
            return false;
        }

        function onTBChange() {
            console.log("Change :: " + this.value());
            var target = $("#target").data("kendoDropDownList").value();  //$("#target").val();
            var q_rate = $("#quote-rate").data("kendoNumericTextBox").value();  //.val();
            var c_rate = $("#complete-rate").data("kendoNumericTextBox").value(); //$("#complete-rate").val();
            //Approve

            //if (target!='' && q_rate>0) {}
            $('#ap_button').attr('disabled', (target=='' || q_rate==0));
            //Complete
            $('#cp_button').attr('disabled', (!readyToComplete()));
        }
        function readyToComplete() {
            if ($("#complete-rate").data("kendoNumericTextBox").value()>0 && $('#log_ready').val()>0) {
                console.log("complete ok");
                return true;
            }
            console.log("complete ng");
            return false;
        }

    </script>
    <?= $this->Form->end(); ?>
    <div>&nbsp</div>
    <!-- batch details grid -->
    <div id="grid"></div>
    <script>
        $(document).ready(function(){
            var dataSource = new kendo.data.DataSource({
                serverFiltering: true,
                transport: {
                    read: "json-<?=$remittanceBatch[0]['batch_id']?>/",
                    dataType: "json",
                    /*function (e) {
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
                pageSize: 25,
                sortable: true,
                serverPaging: false,
                serverSorting: false,
                sort: { field: "id", dir: "asc" }
            });

            $("#grid").kendoGrid({
                columns: [{field: "id",title: "Id", width: 50, attributes:{id:"row-batch-id"}},
                    {field: "account",title: "Account No.", width: 200, attributes:{}},
                    {field: "beneficiary_name",title: "Name"}, {field: "bank_name",title: "Bank Name"},
                    {field: "bank_branch",title: "Bank Branch"}, {field: "province",title: "Province"},
                    {field: "city",title: "City"},
                    {field: "id_number",title: "ID Card", width: 200},
                    {field: "amount",title: "Amount CNY", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                    {field: "convert_amount",title: "Amount USD", format: "{0:n2}", width: 100, attributes:{class:"numeric"}},
                    {field: "convert_rate",title: "Rate", format: "{0:n4}", width: 100, attributes:{class:"numeric"}},
                    {field: "tx_status_name",title: "Status", width: 80},
                    {field: "action", title: "Actions", width: 100, sortable: false, template:"<a class='${action_class}' href='javascript:void(0);' data-id='${id}' data-bid='${batch_id}'>${action}</a>"}
                ],

// data-id=${id}, data-bid=${batch_id}
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
                    pageSizes: true,
                    buttonCount: 2
                },
                filterable: false,
            });
        });
    </script>

    <table cellpadding="0" cellspacing="0" style="table-layout: auto;">
        <thead>
        <tr>
            <th class="numeric"><?= __('#') ?></th>
            <th><?= __('Name') ?></th>
            <th><?= __('Account No.') ?></th>
            <th><?= __('Bank Name') ?></th>
            <th><?= __('Bank Branch') ?></th>
            <th><?= __('Province') ?></th>
            <th><?= __('City') ?></th>
            <th><?= __('ID Card') ?></th>
            <!--            <th><?= __('ID Type') ?></th> -->
            <th class="numeric"><?= __('Amount CNY') ?></th>
            <th class="numeric"><?= __('Amount USD') ?></th>
            <th class="numeric"><?= __('Rate') ?></th>
            <th><?= __('Status') ?></th>
            <th class="actions"><?= __('Actions') ?>
                <div><?= $this->Html->link(__('Set All OK'),'javascript:void(0)',['class'=>'act-all-ok','data-bid'=>$remittanceBatch[0]['batch_id'] ] ) ?></div>
            </th>
        </tr>
        </thead>
        <tbody>
        <?php if (is_array($remittanceBatch)) {
                foreach ($remittanceBatch as $idx=>$batch):
                $convert_rate = ($batch['currency']=='CNY'?$batch['convert_rate']:1/floatval($batch['convert_rate']));
                ?>
        <tr>
            <td class="numeric"><?= h($idx+1) ?></td>
            <td><?= $batch['beneficiary_name'] ?></td>
            <td><?= h($batch['account']) ?></td>
            <td><?= h($batch['bank_name']) ?></td>
            <td><?= h($batch['bank_branch']) ?></td>
            <td><?= h($batch['province']) ?></td>
            <td><?= h($batch['city']) ?></td>
            <td><?= h($batch['id_number']) ?></td>
            <!--            <td><?= h($batch['id_type']) ?></td> -->
            <td class="numeric"><?= $this->Number->precision(($batch['currency']=='CNY'?$batch['amount']:$batch['convert_amount']),2) ?></td>
            <td class="numeric"><?= $this->Number->precision(($batch['convert_currency']=='USD'?$batch['convert_amount']:$batch['amount']),2) ?></td>
            <td class="numeric"><?= $this->Number->precision($convert_rate,4) ?></td>
            <td><?= h($batch['tx_status_name']) ?></td>
            <td class="actions">
                <span><?php if ($batch['tx_status_name']!='OK') print $this->Html->link(__('OK'),'javascript:void(0)',['class'=>'act-ok','data-id'=>$batch['id'], 'data-bid'=>$batch['batch_id']] ) ?></span>
                &nbsp;&nbsp;<span><?php if ($batch['tx_status_name']!='Failed') print  $this->Html->link(__('Failed'),'javascript:void(0)',['class'=>'act-fail','data-id'=>$batch['id'], 'data-bid'=>$batch['batch_id']] ) ?></span>
            </td>
        </tr>
        <?php endforeach;
                }?>
        </tbody>
        <script>
        </script>
    </table>
</div>
