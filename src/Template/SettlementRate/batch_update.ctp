<div class="form search-input">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>

<?= $this->Form->create(null, ['url' => ['action' => 'batchUpdate'] , 'name' => 'ratesUpdate']) ?>
    <fieldset>
        <legend><?= __('Settlement Rate Update') ?></legend>
        <p>Apply the rate below to merchant of FX package 2 when converting CNY to their settlement currently</p>
        
        <p>Last update: <span class="val-lastupdated"><?php echo empty($symbol_rates_last_updated) ? '-' : $symbol_rates_last_updated->format('Y-m-d H:i:s'); ?></span></p>
<table width="100%">
<thead>
<tr><td width="20%">Symbol</td><td width="20%">Rate</td><td>Description</td></tr>
</thead>
<tbody>
<?php foreach ($symbol_rates as $symbol) :?>
<tr><td><?php echo $symbol['rate_symbol']?></td><td><input type="number" required="" name="symbol_rates[<?php echo $symbol['id']?>]" value="<?php echo $symbol['rate_value']?>" step="0.0001" min="0.0001" /></td><td><?php echo $symbol['description']?></td></tr>
<?php endforeach;?>
</tbody>
</table>

    </fieldset>
    <div>
<?= $this->Form->button(__('Update'), ['type' => 'submit', 'class'=>'k-button']); ?>
    </div>

<?= $this->Form->end() ?>

<script>
$(function(){
    $('body').on('submit', 'form[name=ratesUpdate]', function(e){
        e.preventDefault();

        var $form = $(this);
        var data = {};

        $form.find('input[type=number]').each(function(idx, elm){
            var $elm = $(elm);
            data[ $elm.prop('name') ] = $elm.val();
        })
        $.post($form.prop('action'), data, function(rst){
            if(rst.status == 'done' ){
            	if(rst.last_updated)
	                $form.find('.val-lastupdated').text( rst.last_updated )
                alert('Rates has been updated.');
            }

        }).error(function(){
            alert('Sorry, server cannot handle the request. Please try again later.');
        })
    })
})
</script>