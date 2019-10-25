<!-- File: src/Template/Pages/dateform.ctp -->
<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <?= $this->Form->create(null, ['url' => ['controller' => 'CivsReport', 'action' => 'dateform']]) ?>
    <fieldset>
        <legend><?= __('Select Period of Request') ?></legend>

        <?= $this->Form->input('user', ['type'=>'text', 'label' => 'User List', 'id'=>'userlist', 'required' => true]) ?>
        <?= $this->Form->input('startdate', ['type'=>'text', 'label' => 'Start Date', 'id'=>'startdt', 'required' => true]) ?>
        <?= $this->Form->input('enddate', ['type'=>'text', 'label' => 'End Date', 'id'=>'enddt', 'required' => true]) ?>
    </fieldset>
    <div>
        <?= $this->Form->button(__('Submit'), ['type' => 'submit', 'class'=>'left']); ?>
        <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'right']); ?>
    </div>
    <?= $this->Form->end() ?>
</div>
<script>
$(document).ready(function() {
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
    yesterday.setDate(today.getDate() - 30);

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

    var dataSource = new kendo.data.DataSource({
        transport: {
            read: {
                url: '<?=$this->Url->build(["controller" => "CivsReport", "action" => "user_json",]) ?>',
                dataType: "json"
            }
        }
    });

    $("#userlist").kendoDropDownList({
        dataSource: dataSource,
        dataTextField: "username",
        dataValueField: "id",
        animation: false
        /*
         optionLabel: {
         username: "ALL",
         id: ""
         }
         */
    });

});
</script>