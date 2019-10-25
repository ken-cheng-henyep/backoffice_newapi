<!-- File: src/Template/Pages/upload.ctp -->

<div class="users form">
<?= $this->Flash->render('auth') ?>
<?php // = $this->Html->css('wc') ?>
<?php
	//$this->log($this->request->clientIp(), 'debug');
?>
<?= $this->Form->create(null, ['type' => 'file', 'url' => ['controller' => 'RemittanceBatch', 'action' => 'upload']]) ?>
    <fieldset>
        <legend><?= __('Select merchant & file to upload') ?></legend>
        <ul class="fieldlist">
        <li>
            <label for="simple-input">Merchant</label>
            <?=  $this->Form->select(
            'merchant',
            $merchant_lst,
            ['empty' => '(choose one)', 'required' => true, 'id'=>'merchantDropdown']); ?>
        </li>
        <li>
            <label for="">Excel File</label>
            <?= $this->Form->file('upfile', ['id'=>'upfile','required' => true, 'accept'=>'application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ]) /**/?>
            <?=$this->Html->link('Excel Template Download', '/xls/WeCollect_RemittanceInstruction_Form_201703.xlsx',['target' => '_blank']); ?>
        </li>
            <li>
                <label for="">Authorization PDF file</label>
                <?= $this->Form->file('pdfile', ['id'=>'pdfile','required' => true, 'accept'=>'application/pdf' ]) ?>
            </li>
        <li>
            <div id="buttonContainer">
                <?= $this->Form->button(__('Upload'), ['type' => 'submit', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-tick"]); ?>
                <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel"]); ?>
            </div>
        </li>
        </ul>
    </fieldset>

<div>

</div>
<?= $this->Form->end() ?>
<script>
$(document).ready(function() {
    //$("#upfile").kendoUpload();
    $("#merchantDropdown").kendoDropDownList();
    $("#merchantDropdown").data("kendoDropDownList").list.width(800);
    kendo.init("#buttonContainer");
});
</script>
</div>

<div>
<p>
Validation Result:
    <?php
            if (isset($jsons)) {
                $bid = (isset($jsons['data']['batch_id'])?$jsons['data']['batch_id']:false);
                printf("<div>%s</div>", $jsons['msg']);
                //if (isset($jsons['batch_id']))
                if ($bid)
                {
                    print '<div>'.$this->Html->link(sprintf("Batch ID: %s", $bid), ['controller' => 'RemittanceBatch', 'action' => 'view', $bid]).'</div>';
                    //printf("Batch ID: %s<br/>", $jsons['batch_id']);
                } elseif (is_array($jsons['data']['validation_errors'])) {
                    print('<div class="dtable">');
                    print('<div class="drow"><div class="dhead">Row #</div><div class="dhead">Code</div><div class="dhead">Error</div></div>');
                    foreach ($jsons['data']['validation_errors'] as $errors)
                        printf('<div class="drow"><div class="dcell">%s</div><div class="dcell">%s</div><div class="dcell">%s</div></div>', $errors['row'], $errors['error_code'], $errors['error_msg'] );
                    print('</div>');
                }
            }
            ?>
</p>
</div>
