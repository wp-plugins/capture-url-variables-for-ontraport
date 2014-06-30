<?php
/**
 * Plugin Name: OAP UTM WP Plugin
 * Plugin URI: http://www.itmooti.com.au/
 * Description: A plugin to add UTM and Referring Page fields on OAP Smart Forms
 * Version: 1.0
 * Stable tag: 1.0.1
 * Author: ITMOOTI
 * Author URI: http://www.itmooti.com.au/
 */
$utm_fields=array(
	"utm_source"=>"UTM Source",
	"utm_medium"=>"UTM Medium",
	"utm_term"=>"UTM Term",
	"utm_content"=>"UTM Content",
	"utm_campaign"=>"UTM Campaign",
	"afft_"=>"afft_",
	"aff_"=>"aff_",
	"sess_"=>"sess_",
	"ref_"=>"ref_",
	"own_"=>"own_",
	"oprid"=>"oprid",
	"contact_id"=>"contact_id",
);
$utm_extra_fields=array(
	"fname"=>"fname",
	"lname"=>"lname",
	"email"=>"Email",
	"referral_page"=>"Referral Page",
	"var1"=>"var1",
	"var2"=>"var2",
	"var3"=>"var3",
	"var4"=>"var4",
	"var5"=>"var5",
);
class OAPUTM
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;
	private $utm_fields;
	private $url="http://app.itmooti.com/wp-plugins/oap-utm/api.php";
    /**
     * Start up
     */
    public function __construct($utm_fields, $utm_extra_fields){
		$this->utm_fields=$utm_fields;
		$this->utm_extra_fields=$utm_extra_fields;
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_style') );
        add_action( 'admin_menu', array( $this, 'add_oap_utm_page' ) );
        add_action( 'admin_init', array( $this, 'oap_utm_init' ) );
		add_action('wp_head', array($this, 'oap_utm_custom_js'));
    }
	
	public function load_admin_style(){
        wp_enqueue_style( 'chosen_css', plugins_url('js/chosen/chosen.css', __FILE__), false, '1.0.0' );
		wp_enqueue_style( 'oap_utm_css', plugins_url('css/style.css', __FILE__), false, '1.0.0' );
		wp_enqueue_script( 'oap_utm_chosen', plugins_url('js/chosen/chosen.jquery.js', __FILE__), false, '1.0.0' );
		wp_enqueue_script( 'oap_utm_prism', plugins_url('js/js.js', __FILE__), false, '1.0.0' );
	}

    /**
     * Add options page
     */
    public function add_oap_utm_page(){
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin', 
            'OAP UTM Settings', 
            'manage_options', 
            'oap-utm-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page(){
        // Set class property
        $this->options = get_option( 'oap_utm_name' );
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>OAP UTM Settings</h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'oap_utm_group' );   
                do_settings_sections( 'oap-utm-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function oap_utm_init(){
		$this->options = get_option( 'oap_utm_name' );    
        register_setting(
            'oap_utm_group', // Option group
            'oap_utm_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_oap_license', // ID
            'Plugin Credentials', // Title
            array( $this, 'print_plugin_section_info' ), // Callback
            'oap-utm-admin' // Page
        );
		
		add_settings_field(
            'oap_utm_license_key', // ID
            'License Key', // Title 
            array( $this, 'oap_utm_license_key_callback' ), // Callback
            'oap-utm-admin', // Page
            'setting_oap_license' // Section           
        );
		
		if(isset($this->options['oap_utm_license_key']) && $this->options['oap_utm_license_key']!=""){
			$license_key = $this->options['oap_utm_license_key'];
			$request= "verify";
			$postargs = "license_key=".$license_key."&request=".$request;
			$session = curl_init($this->url);
			curl_setopt ($session, CURLOPT_POST, true);
			curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
			curl_setopt($session, CURLOPT_HEADER, false);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			$response = json_decode(curl_exec($session));
			curl_close($session);
			if(isset($response->status) && $response->status=="success"){
				add_settings_section(
					'setting_oap_credentials', // ID
					'OAP Credentials', // Title
					array( $this, 'print_section_info' ), // Callback
					'oap-utm-admin' // Page
				);
		
				add_settings_field(
					'oap_utm_api_version', // ID
					'API Version', // Title 
					array( $this, 'oap_utm_api_version_callback' ), // Callback
					'oap-utm-admin', // Page
					'setting_oap_credentials' // Section           
				);
				
				add_settings_field(
					'oap_utm_api_id', // ID
					'APP ID', // Title 
					array( $this, 'oap_utm_api_id_callback' ), // Callback
					'oap-utm-admin', // Page
					'setting_oap_credentials' // Section           
				);      
		
				add_settings_field(
					'oap_utm_api_key', // ID
					'API Key', // Title 
					array( $this, 'oap_utm_api_key_callback' ), // Callback
					'oap-utm-admin', // Page
					'setting_oap_credentials' // Section           
				);
			
				if(isset($this->options['oap_utm_api_id']) && $this->options['oap_utm_api_id']!="" && isset($this->options['oap_utm_api_key']) && $this->options['oap_utm_api_key']!=""){
					add_settings_section(
						'setting_oap_forms', // ID
						'OAP Forms', // Title
						array( $this, 'get_oap_forms' ), // Callback
						'oap-utm-admin' // Page
					);
				}
			}
			else{
				if(isset($response->message))
					$_SESSION["oap_response"]=$response->message;
				else
					$_SESSION["oap_response"]="Error in license key verification. Try again later";
				add_settings_section(
					'setting_oap_error', // ID
					'License Key Error', // Title
					array( $this, 'print_error' ), // Callback
					'oap-utm-admin' // Page
				);
			}
		}
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ){
		$new_input = array();
        if( isset( $input['oap_utm_api_version'] ) )
            $new_input['oap_utm_api_version'] = sanitize_text_field( $input['oap_utm_api_version'] );
			
		if( isset( $input['oap_utm_license_key'] ) )
            $new_input['oap_utm_license_key'] = sanitize_text_field( $input['oap_utm_license_key'] );
			
		if( isset( $input['oap_utm_api_id'] ) )
            $new_input['oap_utm_api_id'] = sanitize_text_field( $input['oap_utm_api_id'] );
			
		if( isset( $input['oap_utm_api_key'] ) )
            $new_input['oap_utm_api_key'] = sanitize_text_field( $input['oap_utm_api_key'] );
			
		//UTM Extra Fields
		$oap_utm_extra_fields=array();
		if(isset($_POST["oap_utm_extra_fields"])){
			foreach($_POST["oap_utm_extra_fields"] as $k=>$v){
				$oap_utm_extra_fields[]=$v;
			}
		}
		$oap_utm_extra_fields=serialize($oap_utm_extra_fields);
		add_option("oap_utm_extra_fields", $oap_utm_extra_fields) or update_option("oap_utm_extra_fields", $oap_utm_extra_fields);
		
		//Form IDS
		$oap_utm_form_ids=$oap_utm_user_forms=array();
		if(isset($_POST["oap_utm_form_ids"])){
			$oap_utm_form_ids=array();
			foreach($_POST["oap_utm_form_ids"] as $k=>$v){
				$oap_utm_form_ids[]=$v;
				if(isset($_POST["form_id_".$v])){
					foreach($this->utm_extra_fields as $k1=>$v1){
						if(isset($_POST["utm_field_".$k1."_".$v])){
							$oap_utm_user_forms[$v][$k1]=$_POST["utm_field_".$k1."_".$v];
						}
					}
				}
			}
		}
		$oap_utm_form_ids=serialize($oap_utm_form_ids);
		add_option("oap_utm_form_ids", $oap_utm_form_ids) or update_option("oap_utm_form_ids", $oap_utm_form_ids);
		$oap_utm_user_forms=serialize($oap_utm_user_forms);
		add_option("oap_utm_user_forms", $oap_utm_user_forms) or update_option("oap_utm_user_forms", $oap_utm_user_forms);		
		
		//UTM Fields
		$oap_utm_fields=array();
		if(isset($_POST["oap_utm_fields"])){
			$oap_utm_fields=array();
			foreach($_POST["oap_utm_fields"] as $k=>$v){
				$oap_utm_fields[]=$v;
			}
		}
		$oap_utm_fields=serialize($oap_utm_fields);
		add_option("oap_utm_fields", $oap_utm_fields) or update_option("oap_utm_fields", $oap_utm_fields);
		
        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_plugin_section_info(){
        print 'Provide Plugin Credentials below:';
    }
	
	public function print_section_info(){
        print 'Provide OAP Credentials below:';
    }
	public function print_error(){
		echo $_SESSION["oap_response"];
		unset($_SESSION["oap_response"]);
	}

    /** 
     * Get the settings option array and print one of its values
     */
    public function oap_utm_api_version_callback(){
        $ver=isset( $this->options['oap_utm_api_version'] ) ? esc_attr( $this->options['oap_utm_api_version']): "";
		echo '<select id="oap_utm_api_version" name="oap_utm_name[oap_utm_api_version]">
			<option value="api.ontraport.com"'.($ver=="api.ontraport.com"? ' selected="selected"':"").'>V3.0</option>
			<option value="api.moon-ray.com"'.($ver=="api.moon-ray.com"? ' selected="selected"':"").'>V2.4</option>
		</select>';
    }
	
	public function oap_utm_license_key_callback(){
        printf(
            '<input type="text" id="oap_utm_license_key" name="oap_utm_name[oap_utm_license_key]" value="%s" />',
            isset( $this->options['oap_utm_license_key'] ) ? esc_attr( $this->options['oap_utm_license_key']) : ''
        );
    }
	
	public function oap_utm_api_id_callback(){
        printf(
            '<input type="text" id="oap_utm_api_id" name="oap_utm_name[oap_utm_api_id]" value="%s" />',
            isset( $this->options['oap_utm_api_id'] ) ? esc_attr( $this->options['oap_utm_api_id']) : ''
        );
    }
	
	public function oap_utm_api_key_callback(){
        printf(
            '<input type="text" id="oap_utm_api_key" name="oap_utm_name[oap_utm_api_key]" value="%s" />',
            isset( $this->options['oap_utm_api_key'] ) ? esc_attr( $this->options['oap_utm_api_key']) : ''
        );
    }
	
	public function get_oap_forms(){
		set_time_limit(1000);
		$oap_utm_user_forms=get_option("oap_utm_user_forms", "");
		if(!empty($oap_utm_user_forms))
			$oap_utm_user_forms=unserialize($oap_utm_user_forms);
		else
			$oap_utm_user_forms=array();
		if(isset($this->options['oap_utm_api_id']) && $this->options['oap_utm_api_id']!="" && isset($this->options['oap_utm_api_key']) && $this->options['oap_utm_api_key']!="" && isset($this->options['oap_utm_license_key']) && $this->options['oap_utm_license_key']!=""){
			$appid = $this->options['oap_utm_api_id'];
			$key = $this->options['oap_utm_api_key'];
			$license_key = $this->options['oap_utm_license_key'];
			$ver=isset( $this->options['oap_utm_api_version'] ) ? esc_attr( $this->options['oap_utm_api_version']): "api.ontraport.com";
			$reqType= "fetch";
			$postargs = "appid=".$appid."&key=".$key."&return_id=1&reqType=".$reqType;
			$request = "http://".$ver."/fdata.php";
			$session = curl_init($request);
			curl_setopt ($session, CURLOPT_POST, true);
			curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
			curl_setopt($session, CURLOPT_HEADER, false);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			$response = curl_exec($session);
			curl_close($session);
			if($response!="" && strpos($response, "<error>Invalid AppId / Key Combination</error>")===false){
				$forms=array();
				$cnt=0;
				while(($start=strpos($response, '<form'))!==false){
					$cnt++;
					if(($end=strpos($response, '</form>', $start))!==false){
						$form=substr($response, $start, $end-$start+7);
						$id=explode("id='", $form);
						$id=explode("'", $id[1]);
						$id=$id[0];
						$reqType= "fetch";
						$postargs = "appid=".$appid."&key=".$key."&return_id=1&id=".$id."&reqType=".$reqType;
						$request = "http://".$ver."/fdata.php";
						
						$session = curl_init($request);
						curl_setopt ($session, CURLOPT_POST, true);
						curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
						curl_setopt($session, CURLOPT_HEADER, false);
						curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
						$str = curl_exec($session);
						$form_id="";
						if($str!=""){
							if(($start1=strpos($str, 'name="uid"'))!==false && ($start1=strpos($str, 'value="', $start1))!==false){
								$start1+=strlen('value="');
								if(($end1=strpos($str, '"', $start1))!==false){
									$form_id=trim(substr($str, $start1, $end1-$start1));
									$form_fields=array();
									while(($start1=strpos($str, '<input'))!==false){
										if(($end1=strpos($str, '>', $start1))!==false){
											$temp=substr($str, $start1, $end1-$start1+6);
											$field_name=$field_title="";
											if(($start2=strpos($temp, 'name="'))!==false){
												$start2+=6;
												if(($end2=strpos($temp, '"', $start2))!==false){
													$field_name=strip_tags(substr($temp, $start2, $end2-$start2));
													$form_fields[]=array($field_title, $field_name);
												}
											}
										}
										$str=substr($str, $start1+10);
									}
									$forms[]=array(
										"id" => $form_id,
										"title" => strip_tags($form),
										"form_fields" => $form_fields
									);
								}
							}
						}
					}
					$response=substr($response, $start+10);
				}
				?>
                <table class="form-table">	
                    <tr valign="top">
                        <th scope="row">Select Forms<br /><small>Select all forms which need to have the below fields.</small></th>
                        <td>
                        	<?php
							$cnt=0;
							$oap_utm_form_ids=get_option("oap_utm_form_ids", "");
							if(!empty($oap_utm_form_ids))
								$oap_utm_form_ids=unserialize($oap_utm_form_ids);
							else
								$oap_utm_form_ids=array();
							?>
							<select name="oap_utm_form_ids[]" multiple="multiple" id="oap_utm_form_ids" class="owp_utm-multi-select">
							<?php
							foreach($forms as $k=>$v){
								?>
								<option value="<?php echo $v["id"]?>"<?php if(in_array($v["id"], $oap_utm_form_ids)) echo ' selected="selected"';?>><?php echo $v["title"]?></option>
								<?php
								$cnt++;
							}
							?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Select Fields<br /><small>Select all fields which need to have in the forms as hidden fields.</small></th>
                        <td>
                        	<select name="oap_utm_fields[]" multiple="multiple" id="oap_utm_fields" class="owp_utm-multi-select">
							<?php
							$oap_utm_fields=get_option("oap_utm_fields", "");
							if(!empty($oap_utm_fields))
								$oap_utm_fields=unserialize($oap_utm_fields);
							else
								$oap_utm_fields=array();
							foreach($this->utm_fields as $k=>$v){
								?>
								<option value="<?php echo $k?>"<?php if(in_array($k, $oap_utm_fields)) echo ' selected="selected"';?>><?php echo $v?></option>
								<?php
								$cnt++;
							}
							?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Select Extra Fields<br /><small>Select extra fields which need to be selected and assigned for each selected form.</small></th>
                        <td>
                        	<select name="oap_utm_extra_fields[]" multiple="multiple" id="oap_utm_extra_fields" class="owp_utm-multi-select">
							<?php
							$oap_utm_extra_fields=get_option("oap_utm_extra_fields", "");
							if(!empty($oap_utm_extra_fields))
								$oap_utm_extra_fields=unserialize($oap_utm_extra_fields);
							else
								$oap_utm_extra_fields=array();
							foreach($this->utm_extra_fields as $k=>$v){
								?>
								<option value="<?php echo $k?>"<?php if(in_array($k, $oap_utm_extra_fields)) echo ' selected="selected"';?>><?php echo $v?></option>
								<?php
								$cnt++;
							}
							?>
                            </select>
                        </td>
                    </tr>
                    <?php
                    if(count($oap_utm_extra_fields)>0){
						?>
						<tr>
                        	<th colspan="2"><h3>Assign Extra Fields for each Form</h3></th>
                        </tr>
                        <tr>
                        	<td colspan="2">
                            	<?php
								$oap_utm_user_forms=get_option("oap_utm_user_forms");
								if($oap_utm_user_forms)
									$oap_utm_user_forms=unserialize($oap_utm_user_forms);
								else
									$oap_utm_user_forms=array();
								foreach($forms as $k=>$v){
								    if(in_array($v["id"], $oap_utm_form_ids)){
										if(isset($oap_utm_user_forms[$v["id"]]))
											$pos=$v["id"];
										else
											$pos=false;
										?>
                                        <div class="oap_utm_forms">
                                            <h3><label for="form_id_<?php echo $v["id"]?>"><?php echo $v["title"]?></label><input type="checkbox" id="form_id_<?php echo $v["id"]?>" name="form_id_<?php echo $v["id"]?>" value="<?php echo $v["id"]?>"<?php if($pos) echo ' checked="checked"';?> /></h3>
                                            <div class="oap_utm_forms_content"<?php if($pos) echo ' style="display: block"';?>>
                                                <p>Please select the fields of your form to be used by this plugin.</p>
                                                <?php
                                                foreach($this->utm_extra_fields as $k1=>$v1){
                                                    if(in_array($k1, $oap_utm_extra_fields)){
                                                        ?>
                                                        <label for="utm_field_<?php echo $k1."_".$v["id"]?>"><?php echo $v1?></label>
                                                        <select name="utm_field_<?php echo $k1."_".$v["id"]?>">
                                                            <?php
                                                            foreach($v["form_fields"] as $k2=>$v2){
                                                                ?>
                                                                <option value="<?php echo $v2[1]?>"<?php if($pos && $oap_utm_user_forms[$pos][$k1]==$v2[1]) echo ' selected="selected"';?>><?php echo $v2[1].""; ?></option>
                                                                <?php
                                                            }
                                                            ?>
                                                        </select>
                                                        <div class="clr"></div>
                                                        <?php
                                                    }			
                                                }
                                                ?>
                                            </div>
                                       	</div>
                                        <?php
									}
								}
								?>
                            </td>
                        </tr>
						<?php
					}
					?>
                </table>
				<input type="hidden" name="oap_utm_total_forms" value="<?php echo count($forms)?>" />
				<?php
			}
			else{
				echo "Error in fetching data from API. Invalid AppId / Key Combination or Please try again.";
			}
		}
	}
	
	public function oap_utm_custom_js() {
		$oap_utm_form_ids=get_option("oap_utm_form_ids", "");
		if(!empty($oap_utm_form_ids))
			$oap_utm_form_ids=unserialize($oap_utm_form_ids);
		else
			$oap_utm_form_ids=array();
		$oap_utm_fields=get_option("oap_utm_fields", "");
		if(!empty($oap_utm_fields))
			$oap_utm_fields=unserialize($oap_utm_fields);
		else
			$oap_utm_fields=array();
		$oap_utm_user_forms=get_option("oap_utm_user_forms");
		if($oap_utm_user_forms)
			$oap_utm_user_forms=unserialize($oap_utm_user_forms);
		else
			$oap_utm_user_forms=array();
		?>
		<script type="text/javascript">
			oap_utm_forms=new Array();
			oap_utm_forms_fields=new Array();
			<?php
			$i=0;
			foreach($oap_utm_form_ids as $k=>$v){
				?>
				oap_utm_forms[<?php echo $i?>]='<?php echo $v?>';
				oap_utm_forms_fields[<?php echo $i?>]=new Object();
				<?php
				if(isset($oap_utm_user_forms[$v])){
					foreach($oap_utm_user_forms[$v] as $k1=>$v1){
						?>
						oap_utm_forms_fields[<?php echo $i?>].<?php echo $k1?>='<?php echo $v1?>';
						<?php
					}
				}
				$i++;
			}
			?>
			function query_variable(name){
				name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
				var regexS = "[\\?&]"+name+"=([^&#]*)";
				var regex = new RegExp( regexS );
				var results = regex.exec( decodeURIComponent(window.location.href) );
				if( results == null )
					return "";
				else
					return results[1];
			}
			function set_cookie(c_name,value,exdays){
				var exdate=new Date();
				exdate.setDate(exdate.getDate() + exdays);
				var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
				document.cookie=c_name + "=" + c_value;
			}
			function get_cookie(c_name){
				var i,x,y,ARRcookies=document.cookie.split(";");
				for (i=0;i<ARRcookies.length;i++){
					x=ARRcookies[i].substr(0,ARRcookies[i].indexOf("="));
					y=ARRcookies[i].substr(ARRcookies[i].indexOf("=")+1);
					x=x.replace(/^\s+|\s+$/g,"");
					if(x==c_name){
						return unescape(y);
					}
				}
			}
			function get_variable_value($var){
				if($var=="referring_website"){
					check_cookie=get_cookie("website_visited");
					if(check_cookie!=null && check_cookie!=""){
						$val=get_cookie("referring_website");
					}
					else{
						$val=document.referrer;
					}
				}
				else{
					if(query_variable($var)==""){
						$val=get_cookie($var);
					}
					else{
						$val=query_variable($var);
					}
				}
				set_cookie($var, $val);
				return $val;
			}
			jQuery(window).load(function(){
				setTimeout(function(){
					check_cookie=get_cookie("website_visited");
					$utm_fields=new Object();
					<?php
					foreach($this->utm_fields as $k=>$v){
						?>
						$utm_fields.<?php echo $k?>=get_variable_value("<?php echo $k?>", check_cookie);
						<?php
					}
					foreach($this->utm_extra_fields as $k=>$v){
						?>
						$utm_fields.<?php echo $k?>=get_variable_value("<?php echo $k?>", check_cookie);
						<?php
					}
					?>
					jQuery("form").each(function(){
						$this=jQuery(this);
						if($this.find("input[name=uid]").length>0){
							$index=oap_utm_forms.indexOf($this.find("input[name=uid]").val());
							if($index!==-1){
								<?php
								foreach($oap_utm_fields as $k=>$v){
									?>
									if($this.find("input[name=<?php echo $v?>]").length==0){
										$this.prepend('<input type="hidden" name="<?php echo $v?>" id="<?php echo $v?>" value="" />');
									}
									$this.find("input[name=<?php echo $v?>]").val($utm_fields.<?php echo $v?>);
									<?php
								}
								?>
								if(typeof(oap_utm_forms_fields[$index])!='undefined'){
									for (key in oap_utm_forms_fields[$index]) {
										$this.find("input[name="+oap_utm_forms_fields[$index][key]+"]").val($utm_fields[key]);
									}
								}
							}
						}
					});
				}, 2000);
			});
		</script>
		<?php
	}
}

$oap_utm_settings_page = new OAPUTM($utm_fields, $utm_extra_fields);