// JavaScript Document
jQuery(document).ready(function(){
	jQuery(".owp_utm-multi-select").chosen();
	jQuery(".oap_utm_forms h3 input").unbind().change(function(){
		if(jQuery(this).parent().find("input:checked").length>0){
			jQuery(this).parent().parent().find(".oap_utm_forms_content").slideDown();
		}
		else{
			jQuery(this).parent().parent().find(".oap_utm_forms_content").is(":visible")
				jQuery(this).parent().parent().find(".oap_utm_forms_content").slideUp();
			
		}
	});
});