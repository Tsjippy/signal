document.addEventListener('click', async ev=>{
    let target  = ev.target;

    if(target.name == 'save_signal_preferences'){
        let response    = await FormSubmit.submitForm(target, 'signal/save_preferences');
        if(response){
            Main.displayMessage(response, 'success');
        }
    }
});