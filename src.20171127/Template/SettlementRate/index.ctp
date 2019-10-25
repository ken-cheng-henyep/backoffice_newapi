<div class="users form">
    <?= $this->Flash->render('auth') ?>

    <div id="errdialog"></div>

    <fieldset>
        <legend><?= __('Settlement Rate Symbol') ?></legend>
<a class="k-button" href="<?=$this->Url->build(['action'=>'add'])?>"><?=__('Add Symbol')?></a>
<table width="100%">
<thead>
<tr><td width="20%"><?=__('Symbol')?></td><td><?=__('Description')?></td><td width="30%"><?=__('Merchants')?></td></tr>
</thead>
<tbody>
<?php foreach ($symbol_rates as $symbol) :?>
<tr><td><a href="<?=$this->Url->build(['controller'=>'SettlementRate', 'action'=>'edit', $symbol['id']])?>"><?php echo $symbol['rate_symbol']?></a></td><td><?php echo $symbol['description']?></td><td>
<?php echo implode("<br />\r\n", $symbol['merchant_names']);?>
</td></tr>
<?php endforeach;?>
</tbody>
</table>


</fieldset>
</div>