<style>
    @font-face {
        font-family: "Arial Unicode MS";
        src: url("<?=str_replace('.css','',$this->Url->css('fonts/ARIALUNI.ttf'))?>") format("truetype");
    }
    .k-grid {
        font-family: "Arial Unicode MS", "Arial", sans-serif;
    }
    /* Page Template for the exported PDF */
    .page-template {
        font-family: "Arial Unicode MS", "Arial", sans-serif;
        position: absolute;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
    }
</style>
<?= $this->Html->script('jszip.min')?>
<?= $this->Html->script('//kendo.cdn.telerik.com/2016.3.1028/js/pako_deflate.min.js')?>
<?= $this->Html->script('wc')?>
<script type="x/kendo-template" id="page-template">
    <div class="page-template">
        <div class="header">
            <div style="float: right">Page #: pageNum # of #: totalPages #</div>
        </div>
        <div class="watermark">WeCollect</div>
        <div class="footer">
            Page #: pageNum # of #: totalPages #
        </div>
    </div>
</script>
<div id="cdialog"></div>
<div class="remittanceBatch view large-11 medium-10 columns content">
    <?= $this->Form->create(null, ['url' => ['controller' => 'RemittanceBatch', 'action' => 'view', 'id'=>$remittanceBatch[0]['batch_id']]]) ?>
    <table class="vertical-table">
        <tr>
            <th><?= __('Batch Id') ?></th>
            <td><span id="batch_id"><?= h($remittanceBatch[0]['batch_id']) ?></span>&nbsp;
            <?php if (!empty($remittanceBatch[0]['file2'])): ?>
                <b><?= __('Authorization Signature') ?></b>&nbsp;
                <?= $this->Html->link('PDF',["controller" => "RemittanceBatch", "action" =>'serveStaticFile', basename($remittanceBatch[0]['file2'])] )?>
            <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th><?= __('Merchant') ?></th>
            <td><?= h($remittanceBatch[0]['merchant_name']) ?></td>
            <!--
            <td><?/* = $remittanceBatch->has('merchant') ? $this->Html->link($remittanceBatch->merchant->name, ['controller' => 'Merchants', 'action' => 'view', $remittanceBatch->merchant->id]) : '' */?></td>
            -->
        </tr>
        <tr>
            <th><?= __('Merchant ID') ?></th>
            <td><?= h($remittanceBatch[0]['merchant_id']) ?></td>
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
        <?php if (!empty($remittanceBatch[0]['auth_email'])): ?>
        <tr>
            <th><?= __('Authorization') ?></th>
            <td><?= "by: ".$remittanceBatch[0]['auth_email']."<br/>at: ".$remittanceBatch[0]['auth_time']."<br/>from: ".$remittanceBatch[0]['auth_ip'] ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th><?= __('Count') ?></th>
            <td><?= $this->Number->format($remittanceBatch[0]['count']) ?></td>
        </tr>
        <tr>
            <th><?= __('Total amount ').$remittanceBatch[0]['non_cny'] ?></th>
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
            <td><input id="target"><span id="target_txt"></span></td>
        </tr>

    </table>

    <div id="msg"></div>

    <?= $this->Form->hidden('status_name', ['id'=>'status_name', 'value'=>$remittanceBatch[0]['status_name'] ]); ?>
    <?= $this->Form->hidden('log_ready', ['id'=>'log_ready', 'value'=>$remittanceBatch[0]['all_log_set'] ]); ?>
    <?= $this->Form->hidden('quote_rate', ['id'=>'quote_rate', 'value'=>$remittanceBatch[0]['quote_convert_rate'] ]); ?>
    <?= $this->Form->hidden('complete_rate', ['id'=>'complete_rate', 'value'=>$remittanceBatch[0]['complete_convert_rate'] ]); ?>
    <?= $this->Form->hidden('target_id', ['id'=>'target_id', 'value'=>$remittanceBatch[0]['target'] ]); ?>
    <?= $this->Form->hidden('target_name', ['id'=>'target_name', 'value'=> isset($remittanceBatch[0]['target_name'])?$remittanceBatch[0]['target_name']:'' ]); ?>
    <?= $this->Form->hidden('update_url', ['id'=>'update_url', 'value'=>$this->Url->build(["controller" => "RemittanceBatch", "action" => "updateStatus",]) ]); ?>
    <?= $this->Form->hidden('updatelog_url', ['id'=>'updatelog_url', 'value'=>$this->Url->build(["controller" => "RemittanceBatch", "action" => "updateLogStatus",]) ]); ?>
    <?= $this->Form->hidden('apilog_url', ['id'=>'apilog_url', 'value'=>$this->Url->build(["controller" => "RemittanceBatch", "action" => "apiLogJson", ]) ]); ?>
    <?= $this->Form->hidden('admin_action', ['id'=>'admin_action', 'value'=> isset($remittanceBatch[0]['admin_action'])?$remittanceBatch[0]['admin_action']:'' ]); ?>
    <?= $this->Form->hidden('admin_role', ['id'=>'admin_role', 'value'=> isset($remittanceBatch[0]['admin_role'])?$remittanceBatch[0]['admin_role']:'' ]); ?>
<?php
        //make report link
        switch (strtolower($remittanceBatch[0]['status_name'])) {
            case 'processing':
                print $this->Form->hidden('excel_url', ['id'=>'excel_url', 'value'=> $this->Url->build(["controller" => "RemittanceBatch", "action" => "downloadExcel",]) ]);
                print $this->Form->hidden('report_url', ['id'=>'report_url', 'value'=>$this->Url->build(["controller" => "RemittanceBatch", "action" => "downloadReport",]) ]);
                break;
            case 'completed':
            case 'queued':
                print $this->Form->hidden('report_url', ['id'=>'report_url', 'value'=>$this->Url->build(["controller" => "RemittanceBatch", "action" => "downloadReport",]) ]);
                break;
        }
?>
    <?= $this->Form->button('Approve', ['type' => 'button', 'id'=>'ap_button']); ?>
    <?= $this->Form->button('Excel Download', ['type' => 'button', 'id'=>'ex_button']); ?>
    <?= $this->Form->button('Report Download', ['type' => 'button', 'id'=>'rp_button', 'hidden'=>true]); ?>
    <?= $this->Form->button('Decline', ['type' => 'button', 'id'=>'de_button']); ?>
    <?= $this->Form->button('Complete', ['type' => 'button', 'id'=>'cp_button', 'hidden'=>true]); ?>

    <div>&nbsp</div>
    <!-- batch details grid -->
    <div id="grid"></div>

<?= $this->Form->end(); ?>

</div>
