<?php
/**
 * Plugin Name: OAP UTM WP Plugin
 * Plugin URI: http://www.itmooti.com/
 * Description: A plugin to add UTM and Referring Page fields on Ontraport Smart Forms
 * Version: 1.2.0
 * Stable tag: 1.2.0
 * Author: ITMOOTI
 * Author URI: http://www.itmooti.com/
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
	"user_ip_address"=>"IP Address",
	"var1"=>"var1",
	"var2"=>"var2",
	"var3"=>"var3",
	"var4"=>"var4",
	"var5"=>"var5",
);
if(isset($_GET["request"]) && $_GET["request"]=="get_ip"){
	$ip = FALSE;
    if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode (", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
        if ($ip) {
            array_unshift($ips, $ip);
            $ip = FALSE;
        }
        for ($i = 0; $i < count($ips); $i++) {
            if (!eregi ("^(10|172\.16|192\.168)\.", $ips[$i])) {
                if (version_compare(phpversion(), "5.0.0", ">=")) {
                    if (ip2long($ips[$i]) != false) {
                        $ip = $ips[$i];
                        break;
                    }
                } else {
                    if (ip2long($ips[$i]) != -1) {
                        $ip = $ips[$i];
                        break;
                    }
                }
            }
        }
    }
    echo ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
	die;
}
defined('ABSPATH') or die("No script kiddies please!");
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
		if (!session_id()) {
			session_start();
		}
		$this->utm_fields=$utm_fields;
		$this->utm_extra_fields=$utm_extra_fields;
		add_action('admin_enqueue_scripts', array( $this, 'load_admin_style'));
        add_action( 'admin_menu', array( $this, 'add_oap_utm_page' ) );
		add_action('wp_enqueue_scripts', array($this, 'oap_utm_enqueue_js'));
		add_action('wp_head', array($this, 'oap_utm_custom_js'), 300);
		add_action( 'admin_notices', array( $this, 'show_license_info' ) );
		$plugin = plugin_basename(__FILE__);
		add_filter("plugin_action_links_$plugin", array( $this, 'oap_utm_settings_link') );
		add_shortcode('cuv', array($this, 'shortcode_cuv'));
    }
	public function shortcode_cuv($atts){
		$license_key=get_option('oap_utm_license_key', "");
		if(!empty($license_key)){
			$request= "verify";
			$postargs = "domain=".urlencode($_SERVER['HTTP_HOST'])."&license_key=".urlencode($license_key)."&request=".urlencode($request);
			$session = curl_init($this->url);
			curl_setopt ($session, CURLOPT_POST, true);
			curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
			curl_setopt($session, CURLOPT_HEADER, false);
			curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
			$response = json_decode(curl_exec($session));
			curl_close($session);
			if(isset($response->status) && $response->status=="success"){
				if(isset($atts["field"])){
					$field=$atts["field"];
					if(isset($_COOKIE[$field])){
						$value=$_COOKIE[$field];
						if($value=="undefined")
							$value="";
						return $value;
					}
					else
						return "";
				}
			}
		}
	}
	public function oap_utm_enqueue_js(){
		wp_enqueue_script('jquery');
	}
	
	public function show_license_info(){
		$license_key=get_option('oap_utm_license_key', "");
		if(empty($license_key)){
			echo '<div class="updated">
        		<p>OAP UTM WP Plugin: How do I get License Key?<br />Please visit this URL <a href="http://app.itmooti.com/wp-plugins/oap-utm/license/">http://app.itmooti.com/wp-plugins/oap-utm/license/</a> to get a License Key .</p>
	    	</div>';
		}
		if(isset($_SESSION["oap_response"]) && !empty($_SESSION["oap_response"])){
			echo '<div class="error">
        		<p>OAP UTM WP Plugin: '.$_SESSION["oap_response"].'</p>
	    	</div>';
		}
	}
	
	public function load_admin_style(){
        wp_enqueue_style( 'chosen_css', plugins_url('js/chosen/chosen.css', __FILE__), false, '1.0.0' );
		wp_enqueue_style( 'oap_utm_css', plugins_url('css/style.css', __FILE__), false, '1.0.0' );
		wp_enqueue_script( 'oap_utm_chosen', plugins_url('js/chosen/chosen.jquery.js', __FILE__), false, '1.0.0' );
		wp_enqueue_script( 'oap_utm_prism', plugins_url('js/js.js', __FILE__), false, '1.0.0' );
	}
	
	public function oap_utm_settings_link($links) { 
	  	$settings_link = '<a href="options-general.php?page=oap-utm-admin">Settings</a>'; 
	  	array_unshift($links, $settings_link); 
	  	return $links; 
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
        if(isset($_POST["oap_utm_license_key"]))
			add_option("oap_utm_license_key", $_POST["oap_utm_license_key"]) or update_option("oap_utm_license_key", $_POST["oap_utm_license_key"]);
		if(isset($_POST["oap_utm_api_version"]))
			add_option("oap_utm_api_version", $_POST["oap_utm_api_version"]) or update_option("oap_utm_api_version", $_POST["oap_utm_api_version"]);
		if(isset($_POST["oap_utm_app_id"]))
			add_option("oap_utm_app_id", $_POST["oap_utm_app_id"]) or update_option("oap_utm_app_id", $_POST["oap_utm_app_id"]);
		if(isset($_POST["oap_utm_api_key"]))
			add_option("oap_utm_api_key", $_POST["oap_utm_api_key"]) or update_option("oap_utm_api_key", $_POST["oap_utm_api_key"]);
		
		if(isset($_POST["oap_utm_extra_fields"])){
			$oap_utm_extra_fields=array();
			foreach($_POST["oap_utm_extra_fields"] as $k=>$v){
				$oap_utm_extra_fields[]=$v;
			}
			$oap_utm_extra_fields=serialize($oap_utm_extra_fields);
			add_option("oap_utm_extra_fields", $oap_utm_extra_fields) or update_option("oap_utm_extra_fields", $oap_utm_extra_fields);
		}		
		
		if(isset($_POST["oap_utm_form_ids"])){
			$oap_utm_form_ids=$oap_utm_user_forms=array();
			foreach($_POST["oap_utm_form_ids"] as $k=>$v){
				$oap_utm_form_ids[]=$v;
				if(isset($_POST["form_id_".$v])){
					$oap_utm_fields=get_option("oap_utm_fields", "");
					if(!empty($oap_utm_fields))
						$oap_utm_fields=unserialize($oap_utm_fields);
					else
						$oap_utm_fields=array();
					$oap_utm_api_version=get_option('oap_utm_api_version', "");
					$oap_utm_api_version=$oap_utm_api_version!=""? esc_attr($oap_utm_api_version): "api.ontraport.com";
					if($oap_utm_api_version=="api.moon-ray.com"){
						foreach($this->utm_fields as $k1=>$v1){
							if(in_array($k1, $oap_utm_fields)){
								if(isset($_POST["utm_field_".$k1."_".$v])){
									$oap_utm_user_forms[$v][$k1]=$_POST["utm_field_".$k1."_".$v];
								}
							}
						}
					}
					foreach($this->utm_extra_fields as $k1=>$v1){
						if(isset($_POST["utm_field_".$k1."_".$v])){
							$oap_utm_user_forms[$v][$k1]=$_POST["utm_field_".$k1."_".$v];
						}
					}
				}
			}
			$oap_utm_form_ids=serialize($oap_utm_form_ids);
			add_option("oap_utm_form_ids", $oap_utm_form_ids) or update_option("oap_utm_form_ids", $oap_utm_form_ids);
			$oap_utm_user_forms=serialize($oap_utm_user_forms);
			add_option("oap_utm_user_forms", $oap_utm_user_forms) or update_option("oap_utm_user_forms", $oap_utm_user_forms);		
		}
		
		//UTM Fields
		if(isset($_POST["oap_utm_fields"])){
			$oap_utm_fields=array();
			foreach($_POST["oap_utm_fields"] as $k=>$v){
				$oap_utm_fields[]=$v;
			}
			$oap_utm_fields=serialize($oap_utm_fields);
			add_option("oap_utm_fields", $oap_utm_fields) or update_option("oap_utm_fields", $oap_utm_fields);
		}
		$oap_utm_fields=get_option("oap_utm_fields", "");
		if(!empty($oap_utm_fields))
			$oap_utm_fields=unserialize($oap_utm_fields);
		else
			$oap_utm_fields=array();
		foreach($this->utm_fields as $k=>$v){
        	if(in_array($k, $oap_utm_fields)){
				if(isset($_POST["utm_fields_custom_".$k]) && !empty($_POST["utm_fields_custom_".$k])){
					add_option("utm_fields_custom_".$k, $_POST["utm_fields_custom_".$k]) or update_option("utm_fields_custom_".$k, $_POST["utm_fields_custom_".$k]);
				}
			}
		}
		?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>OAP UTM Settings</h2>           
            <form method="post">
           	  	<h3>Plugin Credentials</h3>
                Provide Plugin Credentials below:
                <?php $license_key=get_option('oap_utm_license_key', "");?>
                <table class="form-table">
                	<tr>
                    	<th scope="row">License Key</th>
                        <td><input type="text" name="oap_utm_license_key" id="oap_utm_license_key" value="<?php echo $license_key?>" /></td>
                   	</tr>
              	</table>
				<?php				
				if(!empty($license_key)){
					$request= "verify";
					$postargs = "domain=".urlencode($_SERVER['HTTP_HOST'])."&license_key=".urlencode($license_key)."&request=".urlencode($request);
					$session = curl_init($this->url);
					curl_setopt ($session, CURLOPT_POST, true);
					curl_setopt ($session, CURLOPT_POSTFIELDS, $postargs);
					curl_setopt($session, CURLOPT_HEADER, false);
					curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
					$response = json_decode(curl_exec($session));
					curl_close($session);
					if(isset($response->status) && $response->status=="success"){
						if(isset($response->message))
							$_SESSION["oap_response"]=$response->message;
						$oap_utm_api_version=get_option('oap_utm_api_version', "");
						$oap_utm_app_id=get_option('oap_utm_app_id', "");
						$oap_utm_api_key=get_option('oap_utm_api_key', "");
						?>
						<h3>OAP Credentials</h3>
		                Provide OAP Credentials below:
                        <table class="form-table">
                            <tr>
                                <th scope="row">API Version</th>
                                <td>
                                	<select id="oap_utm_api_version" name="oap_utm_api_version">
                                        <option value="api.ontraport.com"<?php echo ($oap_utm_api_version=="api.ontraport.com"? ' selected="selected"':"")?>>V3.0</option>
                                        <option value="api.moon-ray.com"<?php echo ($oap_utm_api_version=="api.moon-ray.com"? ' selected="selected"':"")?>>V2.4</option>
                                    </select>
                               	</td>
                            </tr>
                            <tr>
                                <th scope="row">APP ID</th>
                                <td><input type="text" name="oap_utm_app_id" id="oap_utm_app_id" value="<?php echo $oap_utm_app_id?>" /></td>
                            </tr>
                            <tr>
                                <th scope="row">API Key</th>
                                <td><input type="text" name="oap_utm_api_key" id="oap_utm_api_key" value="<?php echo $oap_utm_api_key?>" /></td>
                            </tr>
                        </table>
						<?php
						$this->get_oap_forms();
					}
					else{
						if(isset($response->message))
							echo $response->message;
						else
							echo "Error in license key verification. Try again later";
					}
				}
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /** 
     * Print the Section text
     */
    public function get_oap_forms(){
		set_time_limit(5000);
		$oap_utm_user_forms=get_option("oap_utm_user_forms", "");
		if(!empty($oap_utm_user_forms))
			$oap_utm_user_forms=unserialize($oap_utm_user_forms);
		else
			$oap_utm_user_forms=array();
		$oap_utm_api_version=get_option('oap_utm_api_version', "");
		$oap_utm_app_id=get_option('oap_utm_app_id', "");
		$oap_utm_api_key=get_option('oap_utm_api_key', "");
		$oap_utm_license_key=get_option('oap_utm_license_key', "");
		if($oap_utm_app_id!="" && $oap_utm_api_key!=""){
			?>
            <h3>OAP Forms</h3>
            <?php
			$appid = $oap_utm_app_id;
			$key = $oap_utm_api_key;
			$license_key = $oap_utm_license_key;
			$ver=$oap_utm_api_version!=""? esc_attr($oap_utm_api_version): "api.ontraport.com";
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
													if($field_name!="uid"){
														$form_fields[]=array($field_title, $field_name);
													}
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
					//if($cnt>1)
						//break;
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
												if($oap_utm_api_version=="api.moon-ray.com"){
													foreach($this->utm_fields as $k1=>$v1){
														if(in_array($k1, $oap_utm_fields)){
															?>
															<label for="utm_field_<?php echo $k1."_".$v["id"]?>"><?php echo $v1?></label>
															<select name="utm_field_<?php echo $k1."_".$v["id"]?>">
																<option value=""<?php if($pos && isset($oap_utm_user_forms[$pos][$k1]) && $oap_utm_user_forms[$pos][$k1]=="") echo ' selected="selected"';?>>None</option>
																<?php
																foreach($v["form_fields"] as $k2=>$v2){
																	?>
																	<option value="<?php echo $v2[1]?>"<?php if($pos && isset($oap_utm_user_forms[$pos][$k1]) && $oap_utm_user_forms[$pos][$k1]==$v2[1]) echo ' selected="selected"';?>><?php echo $v2[1].""; ?></option>
																	<?php
																}
																?>
															</select>
															<div class="clr"></div>
															<?php
														}
													}
												}
                                                foreach($this->utm_extra_fields as $k1=>$v1){
                                                    if(in_array($k1, $oap_utm_extra_fields)){
                                                        ?>
                                                        <label for="utm_field_<?php echo $k1."_".$v["id"]?>"><?php echo $v1?></label>
                                                        <select name="utm_field_<?php echo $k1."_".$v["id"]?>">
                                                            <option value=""<?php if($pos && isset($oap_utm_user_forms[$pos][$k1]) && $oap_utm_user_forms[$pos][$k1]=="") echo ' selected="selected"';?>>None</option>
															<?php
                                                            foreach($v["form_fields"] as $k2=>$v2){
                                                                ?>
                                                                <option value="<?php echo $v2[1]?>"<?php if($pos && isset($oap_utm_user_forms[$pos][$k1]) && $oap_utm_user_forms[$pos][$k1]==$v2[1]) echo ' selected="selected"';?>><?php echo $v2[1].""; ?></option>
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
						if($v1!="uid"){?>
							oap_utm_forms_fields[<?php echo $i?>].<?php echo $k1?>='<?php echo $v1?>';
						<?php
						}
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
			var $user_ip_address_response;
			function get_variable_value($var){
				if($var=="user_ip_address"){
					$val="";
					jQuery.get("<?php echo plugins_url('capture-url-variables-for-ontraport.php', __FILE__)?>", {request: "get_ip"}, function(response) {
						$user_ip_address_response=response;
						return $user_ip_address_response;
					});
				}
				else{
					if($var=="referring_website"){
						check_cookie=get_cookie("website_visited");
						if(typeof(check_cookie)!='undefined'){
							$val=get_cookie("referring_website");
						}
						else{
							$val=document.referrer;
						}
						if($val=="undefined")
							$val="";
					}
					else{
						if(query_variable($var)==""){
							$val=get_cookie($var);
						}
						else{
							$val=query_variable($var);
						}
						if($val=="undefined")
							$val="";
					}
					set_cookie($var, $val);
					return $val;
				}
			}
			var $utm_fields=new Object();
			function utm_fields_initialize(){
				<?php
				foreach($this->utm_fields as $k=>$v){
					?>
					$utm_fields.<?php echo $k?>=get_variable_value("<?php echo $k?>");
					<?php
				}
				foreach($this->utm_extra_fields as $k=>$v){
					?>
					$utm_fields.<?php echo $k?>=get_variable_value("<?php echo $k?>");
					<?php
				}
				?>
			}
			jQuery(window).load(function(){
				utm_fields_initialize();
				setTimeout(function(){
					console.log($utm_fields);
					jQuery("form").each(function(){
						$this=jQuery(this);
						if($this.find("input[name=uid]").length>0){
							$index=oap_utm_forms.indexOf($this.find("input[name=uid]").val());
							if($index!==-1){
								<?php
								foreach($oap_utm_fields as $k=>$v){
									$oap_utm_api_version=get_option('oap_utm_api_version', "");
									if($oap_utm_api_version!="api.moon-ray.com"){
										?>
										if($this.find("input[name=<?php echo $v?>]").length==0){
											$this.prepend('<input type="hidden" name="<?php echo $v?>" id="<?php echo $v?>" value="" />');
										}
										$this.find("input[name=<?php echo $v?>]").val($utm_fields.<?php echo $v?>);
										<?php
									}
								}
								?>
								if(typeof(oap_utm_forms_fields[$index])!='undefined'){
									for (key in oap_utm_forms_fields[$index]) {
										if(key=="user_ip_address"){
											$this.find("input[name="+oap_utm_forms_fields[$index][key]+"]").val($user_ip_address_response);
										}
										else{
											$this.find("input[name="+oap_utm_forms_fields[$index][key]+"]").val($utm_fields[key]);
										}
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