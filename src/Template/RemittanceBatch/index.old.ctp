<!--
<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('New Remittance Batch'), ['action' => 'add']) ?></li>
        <li><?= $this->Html->link(__('List Merchants'), ['controller' => 'Merchants', 'action' => 'index']) ?></li>
        <li><?= $this->Html->link(__('New Merchant'), ['controller' => 'Merchants', 'action' => 'add']) ?></li>
    </ul>
</nav>
-->
<div class="remittanceBatch index large-12 medium-10 columns content">
    <h3><?= __('Batch Search') ?></h3>
    <?= $this->element('search_form') ?>
    <table cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('id') ?></th>
                <th><?= $this->Paginator->sort('merchant_id') ?></th>
                <th><?= $this->Paginator->sort('upload_time') ?></th>
                <th class="numeric"><?= $this->Paginator->sort('count') ?></th>
                <th class="numeric"><?= __('Total USD') ?></th>
                <th class="numeric"><?= __('Total CNY') ?></th>
                <th><?= $this->Paginator->sort('status') ?></th>
                <th><?= $this->Paginator->sort('channel') ?></th>
                <th class="actions"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($remittanceBatch as $remittanceBatch): ?>
            <tr>
                <td><?= $this->Html->link($remittanceBatch->id, ['controller' => 'RemittanceBatch', 'action' => 'view', $remittanceBatch->id]) ?></td>
<!--                <td><?= $remittanceBatch->has('merchant') ? $this->Html->link($remittanceBatch->merchant->name, ['controller' => 'Merchants', 'action' => 'view', $remittanceBatch->merchant->id]) : '' ?></td> -->
                <td><?= $remittanceBatch->has('merchant') ? $remittanceBatch->merchant->name : '' ?></td>
                <td><?= h($remittanceBatch->upload_time) ?></td>
                <td class="numeric"><?= $this->Number->format($remittanceBatch->count) ?></td>
                <td class="numeric"><?= is_null($remittanceBatch->total_usd)?'N/A':$this->Number->precision($remittanceBatch->total_usd,2) ?></td>
                <td class="numeric"><?= is_null($remittanceBatch->total_cny)?'N/A':$this->Number->precision($remittanceBatch->total_cny,2) ?></td>
                <td><?= h($remittanceBatch->status_name) ?></td>
                <td><?= h($remittanceBatch->target_name) ?></td>
                <td class="actions">
                    <?php $linkname = 'View';
                            if ($remittanceBatch->status==RemittanceReportReader::BATCH_STATUS_QUEUED)
                                $linkname = 'Process';
                            elseif ($remittanceBatch->status==RemittanceReportReader::BATCH_STATUS_PROCESS)
                                $linkname = 'Update';
                            print $this->Html->link(__($linkname), ['action' => 'view', $remittanceBatch->id])
                            ?>
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
