<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('Edit Remittance Log'), ['action' => 'edit', $remittanceLog->id]) ?> </li>
        <li><?= $this->Form->postLink(__('Delete Remittance Log'), ['action' => 'delete', $remittanceLog->id], ['confirm' => __('Are you sure you want to delete # {0}?', $remittanceLog->id)]) ?> </li>
        <li><?= $this->Html->link(__('List Remittance Log'), ['action' => 'index']) ?> </li>
        <li><?= $this->Html->link(__('New Remittance Log'), ['action' => 'add']) ?> </li>
    </ul>
</nav>
<div class="remittanceLog view large-9 medium-8 columns content">
    <h3><?= h($remittanceLog->id) ?></h3>
    <table class="vertical-table">
        <tr>
            <th><?= __('Beneficiary Name') ?></th>
            <td><?= h($remittanceLog->beneficiary_name) ?></td>
        </tr>
        <tr>
            <th><?= __('Account') ?></th>
            <td><?= h($remittanceLog->account) ?></td>
        </tr>
        <tr>
            <th><?= __('Bank Name') ?></th>
            <td><?= h($remittanceLog->bank_name) ?></td>
        </tr>
        <tr>
            <th><?= __('Bank Branch') ?></th>
            <td><?= h($remittanceLog->bank_branch) ?></td>
        </tr>
        <tr>
            <th><?= __('Province') ?></th>
            <td><?= h($remittanceLog->province) ?></td>
        </tr>
        <tr>
            <th><?= __('City') ?></th>
            <td><?= h($remittanceLog->city) ?></td>
        </tr>
        <tr>
            <th><?= __('Currency') ?></th>
            <td><?= h($remittanceLog->currency) ?></td>
        </tr>
        <tr>
            <th><?= __('Convert Currency') ?></th>
            <td><?= h($remittanceLog->convert_currency) ?></td>
        </tr>
        <tr>
            <th><?= __('Id Number') ?></th>
            <td><?= h($remittanceLog->id_number) ?></td>
        </tr>
        <tr>
            <th><?= __('Batch Id') ?></th>
            <td><?= h($remittanceLog->batch_id) ?></td>
        </tr>
        <tr>
            <th><?= __('Id') ?></th>
            <td><?= $this->Number->format($remittanceLog->id) ?></td>
        </tr>
        <tr>
            <th><?= __('Bank Code') ?></th>
            <td><?= $this->Number->format($remittanceLog->bank_code) ?></td>
        </tr>
        <tr>
            <th><?= __('Province Code') ?></th>
            <td><?= $this->Number->format($remittanceLog->province_code) ?></td>
        </tr>
        <tr>
            <th><?= __('Amount') ?></th>
            <td><?= $this->Number->format($remittanceLog->amount) ?></td>
        </tr>
        <tr>
            <th><?= __('Convert Amount') ?></th>
            <td><?= $this->Number->format($remittanceLog->convert_amount) ?></td>
        </tr>
        <tr>
            <th><?= __('Convert Rate') ?></th>
            <td><?= $this->Number->format($remittanceLog->convert_rate) ?></td>
        </tr>
        <tr>
            <th><?= __('Id Type') ?></th>
            <td><?= $this->Number->format($remittanceLog->id_type) ?></td>
        </tr>
        <tr>
            <th><?= __('Status') ?></th>
            <td><?= $this->Number->format($remittanceLog->status) ?></td>
        </tr>
        <tr>
            <th><?= __('Create Time') ?></th>
            <td><?= h($remittanceLog->create_time) ?></td>
        </tr>
        <tr>
            <th><?= __('Update Time') ?></th>
            <td><?= h($remittanceLog->update_time) ?></td>
        </tr>
    </table>
    <div class="row">
        <h4><?= __('Validation') ?></h4>
        <?= $this->Text->autoParagraph(h($remittanceLog->validation)); ?>
    </div>
</div>
