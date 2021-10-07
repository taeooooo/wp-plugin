jQuery(document).ready(function($) {

	//이동
	if( document.snsRegister.email ) {
		setRegisterForm();
	}

	jQuery("#service-accept-all").click(function(){
		if( jQuery(this).is(":checked") == true){
			jQuery("#service-accept").prop("checked", true);
			jQuery("#privacy-accept").prop("checked", true);
			if( document.snsRegister.marketing-accept ) jQuery("#marketing-accept").prop("checked", true);
		}else{
			jQuery("#service-accept").prop("checked", false);
                        jQuery("#privacy-accept").prop("checked", false);
                        if( document.snsRegister.marketing-accept ) jQuery("#marketing-accept").prop("checked", false);
		}
	});

});


function snsRegisterValidation(){
	
	if( document.snsRegister.email ) {
		if( document.snsRegister.email.value == ""){
		  alert("이메일을 입력해주세요");
		  document.snsRegister.email.focus();
		  return false;
		}
	}


	if( document.snsRegister.serviceAccept.checked == false ){
       		alert("서비스 이용약관에 동의해주세요");
       		return false;
        }

        if( document.snsRegister.privacyAccept.checked == false ){
        	alert("개인정보수집 및 활용에 동의해주세요");
        	return false;
        }
	
	if( document.snsRegister.email ) {
	var data = {
                'action': 'my_action',
                'mail': jQuery("#sns-register-email").val()
        };

        // We can also pass the url value separately from ajaxurl for front end AJAX implementations
        jQuery.post(ajax_object.ajax_url, data, function(response) {
                if(response == 'DISABLE'){
                        alert('이미 등록된 이메일입니다 다른 주소를 입력해주세요');
                        return false;
                }else if(response == 'ENABLE'){
			document.snsRegister.submit();
		}
        });
	
	return false;
	}


}

function setRegisterForm(){
  	document.snsRegister.email.focus();
}
