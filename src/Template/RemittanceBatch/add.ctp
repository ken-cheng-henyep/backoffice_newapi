<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('List Remittance Batch'), ['action' => 'index']) ?></li>
        <li><?= $this->Html->link(__('List Merchants'), ['controller' => 'Merchants', 'action' => 'index']) ?></li>
        <li><?= $this->Html->link(__('New Merchant'), ['controller' => 'Merchants', 'action' => 'add']) ?></li>
    </ul>
</nav>
<div class="remittanceBatch form large-9 medium-8 columns content">
    <?= $this->Form->create($remittanceBatch) ?>
    <fieldset>
        <legend><?= __('Add Remittance Batch') ?></legend>
        <?php
            echo $this->Form->input('merchant_id', ['options' => $merchants]);
            echo $this->Form->input('target');
            echo $this->Form->input('file1');
            echo $this->Form->input('file2');
            echo $this->Form->input('file3');
            echo $this->Form->input('file1_md5');
            echo $this->Form->input('count');
            echo $this->Form->input('total_usd');
            echo $this->Form->input('total_cny');
            echo $this->Form->input('total_convert_rate');
            echo $this->Form->input('status');
            echo $this->Form->input('username');
            echo $this->Form->input('ip_addr');
            echo $this->Form->input('settle_time');
            echo $this->Form->input('upload_time');
            echo $this->Form->input('update_time');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
