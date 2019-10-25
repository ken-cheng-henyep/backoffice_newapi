<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Form->postLink(
                __('Delete'),
                ['action' => 'delete', $remittanceLog->id],
                ['confirm' => __('Are you sure you want to delete # {0}?', $remittanceLog->id)]
            )
        ?></li>
        <li><?= $this->Html->link(__('List Remittance Log'), ['action' => 'index']) ?></li>
    </ul>
</nav>
<div class="remittanceLog form large-9 medium-8 columns content">
    <?= $this->Form->create($remittanceLog) ?>
    <fieldset>
        <legend><?= __('Edit Remittance Log') ?></legend>
        <?php
            echo $this->Form->input('beneficiary_name');
            echo $this->Form->input('account');
            echo $this->Form->input('bank_name');
            echo $this->Form->input('bank_branch');
            echo $this->Form->input('bank_code');
            echo $this->Form->input('province');
            echo $this->Form->input('city');
            echo $this->Form->input('province_code');
            echo $this->Form->input('currency');
            echo $this->Form->input('amount');
            echo $this->Form->input('convert_currency');
            echo $this->Form->input('convert_amount');
            echo $this->Form->input('convert_rate');
            echo $this->Form->input('id_number');
            echo $this->Form->input('id_type');
            echo $this->Form->input('batch_id');
            echo $this->Form->input('status');
            echo $this->Form->input('validation');
            echo $this->Form->input('create_time');
            echo $this->Form->input('update_time');
        ?>
    </fieldset>
    <?= $this->Form->button(__('Submit')) ?>
    <?= $this->Form->end() ?>
</div>
