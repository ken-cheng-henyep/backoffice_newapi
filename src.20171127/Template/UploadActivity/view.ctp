<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('Edit Upload Activity'), ['action' => 'edit', $uploadActivity->id]) ?> </li>
        <li><?= $this->Form->postLink(__('Delete Upload Activity'), ['action' => 'delete', $uploadActivity->id], ['confirm' => __('Are you sure you want to delete # {0}?', $uploadActivity->id)]) ?> </li>
        <li><?= $this->Html->link(__('List Upload Activity'), ['action' => 'index']) ?> </li>
        <li><?= $this->Html->link(__('New Upload Activity'), ['action' => 'add']) ?> </li>
    </ul>
</nav>
<div class="uploadActivity view large-9 medium-8 columns content">
    <h3><?= h($uploadActivity->id) ?></h3>
    <table class="vertical-table">
        <tr>
            <th><?= __('Merchant') ?></th>
            <td><?= h($uploadActivity->merchant) ?></td>
        </tr>
        <tr>
            <th><?= __('Currency') ?></th>
            <td><?= h($uploadActivity->currency) ?></td>
        </tr>
        <tr>
            <th><?= __('Source File') ?></th>
            <td><?= h($uploadActivity->source_file) ?></td>
        </tr>
        <tr>
            <th><?= __('Output File') ?></th>
            <td><?= h($uploadActivity->output_file) ?></td>
        </tr>
        <tr>
            <th><?= __('Json File') ?></th>
            <td><?= h($uploadActivity->json_file) ?></td>
        </tr>
        <tr>
            <th><?= __('Username') ?></th>
            <td><?= h($uploadActivity->username) ?></td>
        </tr>
        <tr>
            <th><?= __('Ip Addr') ?></th>
            <td><?= h($uploadActivity->ip_addr) ?></td>
        </tr>
        <tr>
            <th><?= __('Id') ?></th>
            <td><?= $this->Number->format($uploadActivity->id) ?></td>
        </tr>
        <tr>
            <th><?= __('Status') ?></th>
            <td><?= $this->Number->format($uploadActivity->status) ?></td>
        </tr>
        <tr>
            <th><?= __('Upload Time') ?></th>
            <td><?= h($uploadActivity->upload_time) ?></td>
        </tr>
        <tr>
            <th><?= __('Tx Time') ?></th>
            <td><?= h($uploadActivity->tx_time) ?></td>
        </tr>
    </table>
</div>
