console.log('Frontend Content Signal Script Loaded');

document.addEventListener("click", event =>{
	let target = event.target;
    if(target.name == 'send-signal'){
        event.stopImmediatePropagation();
        
        let div = target.closest('#signal-message').querySelector('.signal-message-type');
        if(target.checked){
            div.classList.remove('hidden');
        }else{
            div.classList.add('hidden');
        }
    }
});