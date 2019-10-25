<div class="users form">
    <?= $this->Flash->render('auth') ?>
    <div id="errdialog"></div>

    <?= $this->Form->create(null, ['id'=>'upform', 'type' => 'file', 'url' => ['controller' => 'Holidays', 'action' => 'add']]) ?>
    <fieldset>
        <legend><?= __('Add Public Holiday of Non-working Day') ?></legend>
        <ul class="fieldlist">
            <li>
                <label for="simple-input">Name</label>
                <?= $this->Form->input('name',['type' => 'text','label'=>false,'id'=>'name', 'required' => true ]) ?>
            </li>

            <li>
                <label for="simple-input">Date</label>
                <?= $this->Form->input('holiday_date',['type' => 'text','label'=>false,'id'=>'holiday_date', 'required' => true ]) ?>
            </li>

        </ul>
    </fieldset>
    <div>
    <?= $this->Form->button(__('Add')) ?>
    </div>
<?= $this->Form->end() ?>
    <div class="clearfix"></div>
    <div class="">
        <ul class="fieldlist">
            <li>
                <label for="simple-input">Import HK Holdiay from 1823</label>
        <?php

                $url = $this->Url->build([ "action" => 'importCal', 'region'=>'hk',]);
                print $this->Form->button('Import ', ['type' => 'button', 'id'=>'add_btn', 'onclick'=> "window.location.href = '$url';"]);
        ?>
            </li>
        </ul>
    </div>
    <div id="grid"></div><br /><br /><br />
</div>

<script>
$(document).ready(function() {
    var dataSource = new kendo.data.DataSource({
        serverFiltering: true,
        transport: {
            read: {
                url:'<?=$this->Url->build([ "action" => "search"])?>',
                dataType: "json",

            },
        },
        success: function (data) {
         //   console.log('success');
        },
        change: function (data) {
            console.log('change');
            /*
            console.log('all_log_set:'+data.items[0].all_log_set);
            $('#log_ready').val(data.items[0].all_log_set);
            onTBChange();
            */
        },
        schema: {
            data: "data",
            total: "total",
            model: {
                fields: {
                    holiday_date: { type: "date" },
                    create_time: { type: "date" },
                }
            }
        },
        pageSize: 25,
        sortable: true,
        serverPaging: false,
        serverSorting: false,
        //sort: { field: "id", dir: "desc" }
    });

    $("#grid").kendoGrid({
        columns: [
            {field: "holiday_date",title: "Date", width: 200, format: "{0:yyyy-MM-dd}", type:'date', 
                                sortable: {
                                  initialDirection: "desc"  
                                }
                            },
            {field: "name",title: "Holiday Name", width: 400, attributes:{}},
            // {
            //     field: "",
            //     title: "Edit",
            //     width: 40,
            //     sortable: false,
            //     template: "<a class='' href=\"${action_url}\" >Edit</a> <a class='' href=\"${action_url}\" >Delete</a>"
            // },
            {
                field: "action",
                title: "Actions",
                sortable: false,
                template: "<a class='' href=\"javascript:void(0);\" data-id='${id}' onclick=\"deleteItem('${id}');\">Delete</a>"
            }
        ],
        // javascript:void(0);
        // data-id=${id}, data-bid=${batch_id}
        dataSource: dataSource,
        //height: 420,
        /*change: onGridChange, */
        selectable: "row",
        sortable: {
            mode: "single",
            allowUnsort: true
        },
        pageable: {
            refresh: true,
            //pageSizes: true,
            pageSizes: [10, 25, 50, "All"],
            buttonCount: 2
        },
        filterable: false,
        resizable: true,
        //scrollable: false,
    });

    var holiday_date = $("[name=holiday_date]").kendoDatePicker({
        format: "yyyy-MM-dd",
    }).data("kendoDatePicker");

});

function popup(msg) {
    var dialog = $("#errdialog").data("kendoDialog");
    dialog.content(msg);
    dialog.open();
}

function deleteItem(id){
 //url = 'update-status';
    url = '<?=$this->Url->build([ "action" => "delete" ])?>'
    console.log("deleteItem("+id+") url:"+url);
    //return true;

    $.post(url,{'id':id}, function(rst) {
        if (rst.status == 'done') {
            //success
            $('#grid').data('kendoGrid').dataSource.read();
            //$('#GridName').data('kendoGrid').refresh();
            return true;
        }
    }, 'json');

    console.log('end post');
    return false;
}

</script>
<style>
#grid > .k-grid-header > div > table,
#grid > .k-grid-content > table
{
    width: 100% !important;
}
#grid{height: 300px;}
</style>