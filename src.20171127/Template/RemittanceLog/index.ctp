<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('New Remittance Log'), ['action' => 'add']) ?></li>
    </ul>
</nav>
<div class="remittanceLog index large-9 medium-8 columns content">
    <h3><?= __('Remittance Log') ?></h3>
    <table cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('id') ?></th>
                <th><?= $this->Paginator->sort('beneficiary_name') ?></th>
                <th><?= $this->Paginator->sort('account') ?></th>
                <th><?= $this->Paginator->sort('bank_name') ?></th>
                <th><?= $this->Paginator->sort('bank_branch') ?></th>
                <th><?= $this->Paginator->sort('bank_code') ?></th>
                <th><?= $this->Paginator->sort('province') ?></th>
                <th><?= $this->Paginator->sort('city') ?></th>
                <th><?= $this->Paginator->sort('province_code') ?></th>
                <th><?= $this->Paginator->sort('currency') ?></th>
                <th><?= $this->Paginator->sort('amount') ?></th>
                <th><?= $this->Paginator->sort('convert_currency') ?></th>
                <th><?= $this->Paginator->sort('convert_amount') ?></th>
                <th><?= $this->Paginator->sort('convert_rate') ?></th>
                <th><?= $this->Paginator->sort('id_number') ?></th>
                <th><?= $this->Paginator->sort('id_type') ?></th>
                <th><?= $this->Paginator->sort('batch_id') ?></th>
                <th><?= $this->Paginator->sort('status') ?></th>
                <th><?= $this->Paginator->sort('create_time') ?></th>
                <th><?= $this->Paginator->sort('update_time') ?></th>
                <th class="actions"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($remittanceLog as $remittanceLog): ?>
            <tr>
                <td><?= $this->Number->format($remittanceLog->id) ?></td>
                <td><?= h($remittanceLog->beneficiary_name) ?></td>
                <td><?= h($remittanceLog->account) ?></td>
                <td><?= h($remittanceLog->bank_name) ?></td>
                <td><?= h($remittanceLog->bank_branch) ?></td>
                <td><?= $this->Number->format($remittanceLog->bank_code) ?></td>
                <td><?= h($remittanceLog->province) ?></td>
                <td><?= h($remittanceLog->city) ?></td>
                <td><?= $this->Number->format($remittanceLog->province_code) ?></td>
                <td><?= h($remittanceLog->currency) ?></td>
                <td><?= $this->Number->format($remittanceLog->amount) ?></td>
                <td><?= h($remittanceLog->convert_currency) ?></td>
                <td><?= $this->Number->format($remittanceLog->convert_amount) ?></td>
                <td><?= $this->Number->format($remittanceLog->convert_rate) ?></td>
                <td><?= h($remittanceLog->id_number) ?></td>
                <td><?= $this->Number->format($remittanceLog->id_type) ?></td>
                <td><?= h($remittanceLog->batch_id) ?></td>
                <td><?= $this->Number->format($remittanceLog->status) ?></td>
                <td><?= h($remittanceLog->create_time) ?></td>
                <td><?= h($remittanceLog->update_time) ?></td>
                <td class="actions">
                    <?= $this->Html->link(__('View'), ['action' => 'view', $remittanceLog->id]) ?>
                    <?= $this->Html->link(__('Edit'), ['action' => 'edit', $remittanceLog->id]) ?>
                    <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $remittanceLog->id], ['confirm' => __('Are you sure you want to delete # {0}?', $remittanceLog->id)]) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="paginator">
        <ul class="pagination">
            <?= $this->Paginator->prev('< ' . __('previous')) ?>
            <?= $this->Paginator->numbers() ?>
            <?= $this->Paginator->next(__('next') . ' >') ?>
        </ul>
        <p><?= $this->Paginator->counter() ?></p>
    </div>
</div>
