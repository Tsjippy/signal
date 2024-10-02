console.log('Frontend Content Signal Script Loaded');

document.addEventListener("click", event =>{
	let target = event.target;
    if(target.name == 'send_signal'){
        let div = target.closest('#signalmessage').querySelector('.signalmessagetype');
        if(target.checked){
            div.classList.remove('hidden');
        }else{
            div.classList.add('hidden');
        }
    }
});