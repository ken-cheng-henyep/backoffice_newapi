<!-- File: src/Template/Pages/dateform.ctp -->
<div class="users form">
<?php
/*
	$this->Html->scriptStart(['block' => true]);
	$default_date = date('Y-m-d', strtotime('-1 day'));
	//echo "alert('I am in the JavaScript');";
	print '$( function() {
		$("#startdt").datepicker({dateFormat: "yy-mm-dd", minDate: "2016-1-1", defaultDate: -1});
		$("#startdt").datepicker( "setDate", "'.$default_date.'" );

		$("#enddt").datepicker({dateFormat: "yy-mm-dd", defaultDate: -1});
		$("#enddt").datepicker( "setDate", "'.$default_date.'" );
		} );';
	$this->Html->scriptEnd();
	//print $this->fetch('script');
	//$this->log($this->request->clientIp(), 'debug');
*/
?>
<?= $this->Flash->render('auth') ?>
<h3><?=__('Aggregated Data Download')?></h3>
<?= $this->Form->create(null, ['url' => ['controller' => 'TransactionLog', 'action' => 'dateform'], 'target'=>'report_download']) ?>
    <fieldset>
        <legend><?= __('Select Date Period of Transaction') ?></legend>
		<?= $this->Form->input('startdate', ['type'=>'text', 'label' => 'Start Date', 'id'=>'startdt', 'required' => true]) ?>
		<?= $this->Form->input('enddate', ['type'=>'text', 'label' => 'End Date', 'id'=>'enddt', 'required' => true]) ?>
    </fieldset>
<div>
<input type="hidden" name="callback" />
<?= $this->Form->button(__('Submit'), ['type' => 'submit', 'class'=>'left']); ?>
<?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'right']); ?>
</div>
<?= $this->Form->end() ?>
</div>
<?=$this->Html->script('queuejob')?>
<script>

startQueueJob.cancelUrl = '<?=$this->Url->build(['controller'=>'QueueJob','action'=>'cancel'])?>';
startQueueJob.checkUrl = '<?=$this->Url->build(['controller'=>'QueueJob','action'=>'check'])?>';
$(function(){

	function startChange() {
		var startDate = start.value(),
				endDate = end.value();

		if (startDate) {
			startDate = new Date(startDate);
			startDate.setDate(startDate.getDate());
			end.min(startDate);
		} else if (endDate) {
			start.max(new Date(endDate));
		} else {
			endDate = new Date();
			start.max(endDate);
			end.min(endDate);
		}
	}

	function endChange() {
		var endDate = end.value(),
				startDate = start.value();

		if (endDate) {
			endDate = new Date(endDate);
			endDate.setDate(endDate.getDate());
			start.max(endDate);
		} else if (startDate) {
			end.min(new Date(startDate));
		} else {
			endDate = new Date();
			start.max(endDate);
			end.min(endDate);
		}
	}

    var today = new Date();
    var yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);

	var start = $("#startdt").kendoDatePicker({
	    value: yesterday,
		format: "yyyy/MM/dd",
		change: startChange
	}).data("kendoDatePicker");

	var end = $("#enddt").kendoDatePicker({
        value: new Date(),
		format: "yyyy/MM/dd",
		change: endChange
	}).data("kendoDatePicker");

	start.max(end.value());
	end.min(start.value());

	$('body').on('submit', 'form', function(evt){
		evt.preventDefault();
		var $form = $(this);
        // Fake amount
		startQueueJob($form.prop('action'), $form.serialize(), 5000 );
	});

})

    </script>
    <style>

    .hidden{display:none; visibility: hidden;}
    </style>
