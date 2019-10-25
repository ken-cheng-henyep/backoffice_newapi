<!--
<nav class="large-3 medium-4 columns" id="actions-sidebar">
    <ul class="side-nav">
        <li class="heading"><?= __('Actions') ?></li>
        <li><?= $this->Html->link(__('New Upload Activity'), ['action' => 'add']) ?></li> 
    </ul>
</nav>
-->
<div class="uploadActivity index large-10 medium-8 columns content">
    <h3><?= __('Upload Activity') ?></h3>
    <table cellpadding="0" cellspacing="0" style="table-layout: auto;">
        <thead>
            <tr>
                <th><?= $this->Paginator->sort('id') ?></th>
                <th><?= $this->Paginator->sort('status')	?></th>
                <th><?= $this->Paginator->sort('merchant_name') ?></th>
                <th><?= $this->Paginator->sort('merchant_id','Merchant Id') ?></th>
<!--                <th><?= $this->Paginator->sort('currency') ?></th>  -->
                <th><?= $this->Paginator->sort('upload_time') ?></th>
				<th><?= $this->Paginator->sort('update_time') ?></th>
                <th><?= $this->Paginator->sort('tx_time') ?></th>
				<th><?= $this->Paginator->sort('tx_end_time') ?></th>
				<th><?= $this->Paginator->sort('settle_time') ?></th>
<!--                <th><?= $this->Paginator->sort('source_file') ?></th> -->
<!--                <th><?= $this->Paginator->sort('output_file') ?></th> -->
<!--                <th><?= $this->Paginator->sort('json_file') ?></th> -->
                <th><?= $this->Paginator->sort('username') ?></th>
<!--                <th><?= $this->Paginator->sort('ip_addr') ?></th> -->
<!--                <th class="actions"><?= __('API URL') ?></th> -->
                <th class="actions"><?= __('Actions') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($uploadActivity as $uploadActivity): ?>
            <tr>
                <td><?= $this->Number->format($uploadActivity->id) ?></td>
                <td><?php //= $this->Number->format($uploadActivity->status) 
					print ($uploadActivity->status===$status_ok?"LIVE":"PENDING");
				?></td>
                <td><?= h(is_null($uploadActivity->merchant)?'':$uploadActivity->merchant->name) ?></td>
                <td><?= h($uploadActivity->merchant_id) ?></td>
<!--				<td><?= h($uploadActivity->merchant) ?></td>  -->
<!--                <td><?= h($uploadActivity->currency) ?></td> -->
                <td><?= h($uploadActivity->upload_time) ?></td>
				<td><?= h($uploadActivity->update_time) ?></td>
                <td><?= h($uploadActivity->tx_time->format('d/m/Y')) //Date only ?></td>
				<td><?= h($uploadActivity->tx_end_time->format('d/m/Y')) ?></td>
				<td><?= h($uploadActivity->settle_time->format('d/m/Y')) ?></td>
<!--                <td><?= h($uploadActivity->source_file) ?></td> -->
<!--                <td style="width: auto;"><?= h($uploadActivity->output_file) ?></td> -->
<!--                <td><?= h($uploadActivity->json_file) ?></td>  -->
                <td><?= h($uploadActivity->username) ?></td>
<!--                <td><?= h($uploadActivity->ip_addr) ?></td> 
                <td><?php
/*			$url = sprintf('http://%s?merchant_id=%s&date=%s&currency=%s','/report/download/settlement.json',$uploadActivity->merchant_id, $uploadActivity->settle_time->format('Ymd'), $uploadActivity->currency);
			print $url; 
			$this->log($this->request->here ,'debug');
*/
		?></td>
-->
                <td class="actions">
                    <div><?= $this->Html->link(__('View File'), ['action' => 'view_file', $uploadActivity->id]) ?></div>
					<div><?= $this->Html->link(__('View JSON'), ['action' => 'view_file', 'json'=>'true', $uploadActivity->id], ['target' => '_blank']) ?></div>
					<div>
                    <?php 
						/*if ($uploadActivity->status===$status_ok)
							print ($this->Html->link(__('Delete'), ['action' => 'approve', 'type'=>'false', $uploadActivity->id], ['confirm' => __('Are you sure you want to Delete # {0} [{1}]?', $uploadActivity->id, $uploadActivity->settle_time)])); 
						else
						 */
						if ($uploadActivity->status===$status_pending)
							print ($this->Html->link(__('Approve'), ['action' => 'approve', 'type'=>'true', $uploadActivity->id], ['confirm' => __('Are you sure you want to Approve # {0} [{1}]?', $uploadActivity->id, $uploadActivity->settle_time)])); 
					?>
					</div>
<!--                    <?= $this->Form->postLink(__('Delete'), ['action' => 'delete', $uploadActivity->id], ['confirm' => __('Are you sure you want to delete # {0}?', $uploadActivity->id)]) ?>
-->
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
