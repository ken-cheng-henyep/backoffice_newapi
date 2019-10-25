<div class="remittanceBatch index large-12 medium-10 columns content">
    <h3><?= __('Add Merchant') ?></h3>
    <div class="users form">
        <?= $this->Flash->render('auth') ?>
        <?= $this->Form->create(null, ['id'=>"mform",'url' => ['controller' => 'Merchants', 'action' => 'add']]) ?>
        <ul class="fieldlist">
            <li>
                <label for="">Master Merchant:</label>
                <?=  $this->Form->select('master_merchant',
                $master_lst,
                ['empty' => '(choose one)', 'required' => true, 'id'=>'merchantDropdown', 'data-fieldname'=>'Master Merchant']); ?>
            </li>
            <li>
                <label for="simple-input">Merchant Name:</label>
                <input type="text" id="name" name="name" class="k-textbox" placeholder="" style="width: 220px;" required="true" data-fieldname="Merchant Name"/>
            </li>
            <li>
                <label for="simple-input">Merchant ID:</label>
                <input type="text" id="mid" name="mid" class="k-textbox" placeholder="" style="width: 220px;" required="true" data-fieldname="Merchant ID"
                       data-available="true" data-available-msg="Merchant ID has already been used"/>
            </li>
            <li>
                <label for="">Account Type:</label>
                <input id="actypeDropdown" name="processor_account_type"/>
            </li>
            <li>
                <span>Enabled </span><?= $this->Form->checkbox('enabled',[])?>
            </li>

        </ul>
        <div>&nbsp;</div>

        <div id="tabstrip">
            <ul>
                <li>Settlement</li>
                <li>Remittance</li>
            </ul>
            <!-- Settlement Tab -->
            <div class="tab-col">
                <div>
                    <span class="tab-col-lbl" for="simple-input">MDR Fee %</span>
                    <input id="percent-mdr_fee" name="settle_fee" placeholder="" style="width: 220px;" required="true" data-fieldname="MDR Fee %"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">MDR Min Fee</span>
                    <input id="numt-mdr_min_fee" name="settle_min_fee_cny" placeholder="" style="width: 220px;" required="true" data-fieldname="MDR Min Fee"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">Refund Fee</span>
                    <input id="numt-refund_fee" name="refund_fee_cny" style="width: 220px;" required="true" data-fieldname="Refund Fee"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">FX Package</span>
                    <input id="fx-package-ddown" name="settle_option"/>
                </div>
                <!-- For package 2 merchant -->
                <div>
                    <span class="tab-col-lbl" for="simple-input">Settlement Rate Symbol</span>
                    <input id="settle-rate-symbol-ddown" name="settle_rate_symbol"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">Rounding Precision</span>
                    <input id="rounding-ddown" name="round_precision"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">Processor Settlement Currency</span>
                    <input id="pr-settle-ddown" name="processor_settle_currency"/>
                </div>
                <div>
                    <span class="tab-col-lbl" for="simple-input">FX source</span>
                    <input id="fx-source-ddown" name="fx_source"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Settlement Currency</span>
                    <input id="settle-symbol-ddown" name="settle_currency"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Settlement Handling Fee</span>
                    <input id="numt-settle-handle-fee" name="settle_handling_fee" required="true" data-fieldname="Settlement Handling Fee"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Settlement Bank Account</span>
                    <input id="settle-bank-account" name="settle_bank_account" data-fieldname="Settlement Bank Account" value="<?=$merchant['settle_bank_account']?>"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Settlement Bank Name</span>
                    <input id="settle-bank-name" name="settle_bank_name" data-fieldname="Settlement Bank Name" value="<?=$merchant['settle_bank_name']?>"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">MDR Fee Billed Separately</span>
                    <?= $this->Form->checkbox('settle_mdr_fee_separated',['checked'=>"{$merchant['settle_mdr_fee_separated']}"])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Report Recipient Email (separated by , or ;)</span>
                    <textarea id="recipient-email" name="recipient_email" rows="3" cols="10" type="textarea" multiple="true" data-multipleemails-msg="Please enter email list correctly."></textarea>
                </div>
            </div>
            <!-- Remittance Tab -->
            <div class="tab-col">
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Remittance Rate Symbol</span>
                    <input id="rm-symbol-ddown" name="remittance_symbol"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Cross Border Remittance Fee %</span>
                    <input id="percent-remittance-fee" name="remittance_fee" data-fieldname="Cross Border Remittance Fee %" />
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Cross Border Remittance Min Fee</span>
                    <input id="numt-remittance-min-fee" name="remittance_min_fee" data-fieldname="Cross Border Remittance Min Fee" />
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Fee Bearer</span>
                    <input id="remittance_fee_type" name="remittance_fee_type"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Local Remittance Enabled</span>
                    <?= $this->Form->checkbox('local_remittance_enabled',["id"=>"local_remittance_enabled"])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Local Remittance Fee</span>
                    <input id="numt-local-remittance-fee" name="local_remittance_fee" data-fieldname="Local Remittance Fee"/>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Pre-authorized Remittance</span>
                    <?= $this->Form->checkbox('remittance_preauthorized',[])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Remittance API Enabled</span>
                    <?= $this->Form->checkbox('remittance_api_enabled',[])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Skip Balance Check</span>
                    <?= $this->Form->checkbox('skip_balance_check',[])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Remittance Netting</span>
                    <?= $this->Form->checkbox('remittance_netting',[])?>
                </div>
                <div class="is_master">
                    <span class="tab-col-lbl" for="simple-input">Remittance Fee Billed Separately</span>
                    <?= $this->Form->checkbox('remittance_fee_separated',['checked'=>"{$merchant['remittance_fee_separated']}"])?>
                </div>
            </div>
        </div>


        <div id="buttonContainer">
            <?= $this->Form->button(__('Add'), ['type' => 'button', 'class'=>'kdbutton', 'data-role'=>"button", 'data-sprite-css-class'=>"k-icon k-i-search", 'onclick'=>'sendForm()']); ?>
            <?= $this->Form->button(__('Reset'), ['type' => 'reset', 'class'=>'kdbutton', 'data-role'=>"button", 'data-icon'=>"cancel", 'onclick'=>"$('form')[0].reset()"]); ?>
        </div>

        <div>&nbsp;</div>
        <?= $this->Form->end() ?>
        <script>
            $(document).ready(function() {
                //default search
                $("#merchantDropdown").kendoDropDownList({
                    autoWidth: true,
                    }
                );
                $("#merchantDropdown").data("kendoDropDownList").list.width(600);

                $("#actypeDropdown").kendoDropDownList({
                    dataSource: [
                        {value:'1', name:"Online Bank Payment"}, {value:'2', name:"Quick Payment"}, {value:'3', name:"WeChat Pay"}, {value:'4', name:"Alipay"}
                    ],
                    dataTextField: "name",
                    dataValueField: "value",
                });
                $("#actypeDropdown").data("kendoDropDownList").list.width(300);

                $("#rounding-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "text",
                    dataSource: [
                        { text: "2" },
                        { text: "1" },
                    ]
                });
                $("#fx-package-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "value",
                    dataSource: [
                        { text: "Day of transaction", value: "1" },
                        { text: "Day of settlement", value: "2" },
                        { text: "No FX conversion", value: "3" },
                    ]
                });
                $("#pr-settle-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "text",
                    dataSource: [
                        { text: "CNY" },
                        { text: "USD" },
                        { text: "HKD" },
                        { text: "EUR" },
                    ]
                });
                $("#fx-source-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "value",
                    dataSource: [
                        { text: "WeCollect", value: "1" },
                        { text: "WeChat", value: "2" },
                    ]
                });
                $("#remittance_fee_type").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "value",
                    dataSource: [
                        { text: "Merchant bears the fee", value: "1" },
                        { text: "Merchants client bears the fee", value: "2" },
                    ]
                });
                $("#settle-symbol-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "text",
                    dataSource: [
                        { text: "USD" },
                        { text: "HKD" },
                        { text: "EUR" },
                        { text: "GBP" },
                    ]
                });
                $("#settle-rate-symbol-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "text",
                    dataSource: [
                        { text: "CNYUSD_S1" },
                        { text: "CNYUSD_S2" },
                        { text: "CNYUSD_S3" },
                        { text: "CNYUSD_S4" },
                        { text: "CNYHKD_S1" },
                        { text: "CNYHKD_S2" },
                    ]
                });
                $("#rm-symbol-ddown").kendoDropDownList({
                    dataTextField: "text",
                    dataValueField: "value",
                    dataSource: [
                        { text: "USDR01", value: "USDR01" },
                        { text: "HKDR01", value: "HKDR01" }
                    ]
                });

                $("input[id|='numt']").kendoNumericTextBox({
                    value: null,
                    min: 0,
                    max: 9999,
                    step: 10,
                    format: "n2",
                    decimals: 2,
                    //change: onTBChange,
                });
                $("input[id|='percent']").kendoNumericTextBox({
                    value: null,
                    min: 0,
                    max: 100,
                    step: 1,
                    format: "n2",
                    decimals: 2,
                });
                //Tab
                $("#tabstrip").kendoTabStrip();
                var tabstrip = $("#tabstrip").kendoTabStrip().data("kendoTabStrip");
                //select 1st tab
                tabstrip.select(0);

                var isMaster = false;
                $(".is_master").hide();

                $("#merchantDropdown").change(onIdChange);
                $("#mid").change(onIdChange);
                $('#local_remittance_enabled').click(function() {
                    var local_rm = this.checked;
                    //var local_rm = $("#local_remittance_enabled").is(':checked');
                    console.log("checkLocalRm:"+local_rm+" master:"+isMaster);

                    if (isMaster) {
                        if (local_rm) {
                            $('#numt-local-remittance-fee').prop('required', true);
                            $('#percent-remittance-fee').prop('required', false);
                            $('#numt-remittance-min-fee').prop('required', false);
                        } else {
                            $('#numt-local-remittance-fee').prop('required', false);
                            $('#numt-local-remittance-fee').data("kendoNumericTextBox").value(null);
                            $('#percent-remittance-fee').prop('required', true);
                            $('#numt-remittance-min-fee').prop('required', true);

                        }
                    }

                });

                // check selected master & merchant id
                function onIdChange() {
                    var mid = $("#mid").val();
                    var masterid = $("#merchantDropdown").data("kendoDropDownList").value();
                    console.log("onIdChange mid="+ mid);
                    console.log("onIdChange master="+ masterid);
                    isMaster = (mid != '' && mid == masterid);
                    //if (mid != '' && mid == masterid) {
                    if (isMaster) {
                        //master merchant
                        $('#numt-settle-handle-fee').prop('required',true);
                        $('#percent-remittance-fee').prop('required',true);
                        $('#numt-remittance-min-fee').prop('required',true);
                        $('#settle-bank-account').prop('required',true);
                        $('#settle-bank-name').prop('required',true);
                        $(".is_master").show();
                    } else {
                        $('#numt-settle-handle-fee').prop('required',false);
                        $('#percent-remittance-fee').prop('required',false);
                        $('#numt-remittance-min-fee').prop('required',false);
                        $('#settle-bank-account').prop('required',false);
                        $('#settle-bank-name').prop('required',false);
                        $(".is_master").hide();
                        $(".is_master input").val(null);
                        $(".is_master textarea").val(null);
                        console.log("reset input");
                    }
                }

            });
            //ready end

            var validator = $("#mform").kendoValidator({
                rules: {
                    multipleemails: function (input) {
                        if (input.is("[data-multipleemails-msg]") && input.val() != "")
                        {
                            var elist = input.val().replace(/\,/g, ";");
                            console.log("email list:"+elist);
                            var emailsArray = elist.split(";");
                            for (var i=0; i < emailsArray.length; i++)
                            {
//console.log("email:"+emailsArray[i]);
//return validateEmail(emailsArray[i].trim());
                                if ((emailsArray[i].trim() != "") && (validateEmail(emailsArray[i].trim()) == false))
                                {
                                    return false;
                                }
                                console.log("email ok:"+emailsArray[i]);
                            }

                        }
                        return true;
                    },
                    availability: function(input) {

                        var validate = input.data('available');

                        if (typeof validate !== 'undefined' && validate !== false) {

                            //var url = "<?=$validate_url?>";
                            //input.data('availableURL');

                            // cache the field's id
                            var id = input.attr('id');
                            // new up a new cache object for this field if one doesn't already eist
                            var cache = availability.cache[id] = availability.cache[id] || {};
                            // set our status to checking
                            cache.checking = true;
                            console.log("set checking");
                            console.log(cache);
                            // pull the url and message off of the proper data attributes
                            var settings = {
                                //url: input.data('availableUrl') || '',
                                url: "<?=$validate_url?>",
                                message: kendo.template(input.data('availableMsg')) || ''
                            };

                            // if the value in the cache and the current input value are the same
                            // and the cached state is valid...
                            if (cache.value === input.val() && cache.valid) {
                                // the value is available
                                return true;
                            }
                            // if the value in the cache and the input value are the same
                            // and the cached state is not valid...
                            if (cache.value === input.val() && !cache.valid) {
                                // the value is not available
                                cache.checking = false;
                                return false;
                            }

                            // go to the ajax check
                            availability.check(input, settings);
                            // return false which goes into 'checking...' mode
                            return false;
                        }

                        // this rule does not apply to this field
                        return true;
                    },
                },
                messages: {
                    required: function(input) {
                        return getRequiredMessage(input);
                    },
                    availability : function(input) {

                        var id = input.attr('id');
                        var msg = kendo.template(input.data('availableMsg') || '');
                        var cache = availability.cache[id];

                        console.log("get available msg:");
                        console.log(cache);
                        if (cache.checking)
                        {
                            return "Checking...";
                        } else {
                            return msg(input.val());

                        }
                    },
                }
            }).data("kendoValidator");

            var availability = {
                cache: {},
                check: function(element, settings) {

                    var id = element.attr('id');
                    var cache = this.cache[id] = this.cache[id] || {};

                    console.log("availability check: "+element.val());
                    console.log(cache);
                    $.ajax({
                        url: settings.url,
                        dataType: 'json',
                        data: { id: element.val() },
                        success: function(data) {
// the `data` object returns true or false
// based on the availability of the value
                            if (data == null || data === "null")
                                cache.valid = true;
                            else
                                cache.valid = false;
                            console.log( "Data Loaded: " + data );

// set the value on the cache object so
// that it can be referenced in the next validation run
                            //cache.valid = data;

                        },

                        failure: function() {
// the ajax call failed so just set the field
// as valid since we don't know for sure that it's not
                            cache.valid = true;
                        },

                        complete: function() {

// trigger validation again
                            validator.validateInput(element);

// cache the inputs value
                            cache.value = element.val();
                        }
                    });
                }
            };

            function validateEmail(email) {
                var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(email);
            }

            function getRequiredMessage(input) {
                //console.log("getRequiredMessage:"+input.data("fieldname"));
                return "Please enter "+input.data("fieldname");
            }

            function sendForm() {
                console.log("send form");
/*
                var validator = $("#mform").kendoValidator({
                    messages: {
                        // overrides the built-in message for the required rule
                        required: function(input) {
                            return getRequiredMessage(input);
                        },
                        unique: "Merchant ID has already been used.",
                    },
                    rules: {
                        unique: function(input) {
                            if (input.is("[name=mid]")) {
                                console.log("check mid:"+input.val());
                                //return input.val() === "acetop";
                                $.post( "<?=$validate_url?>", { id: input.val()})
                                    .done(function( data ) {
                                        if (data == null || data === "null")
                                            return true;
                                        console.log( "Data Loaded: " + data.name );
                                        return false;
                                    });
                                //TODO: http://www.telerik.com/blogs/extending-the-kendo-ui-validator-with-custom-rules
                            }
                            return true;
                        }
                    }
                }).data("kendoValidator");
                if (validator.validate()) {
                    $("#mform").submit();
                } else {
                    console.log("form NG");
                }
*/
                $("#mform").submit();
            };

            function onDsChange(cnt) {
                console.log("onDsChange="+cnt);
                if (cnt>0)
                    $("#dl-excel").show();
                else
                    $("#dl-excel").hide();
            }

        </script>
    </div>
</div>
