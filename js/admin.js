import {copyFormInput, fixNumbering, removeNode} from '../../forms/js/form_exports.js';

document.addEventListener('click', function(event) {
	let target = event.target;
	
	//add element
	if(target.matches('.add')){
		copyFormInput(target.closest(".clone-div"));

		fixNumbering(target.closest('.clone-divs-wrapper'));

		target.remove();
	}
	
	//remove element
	else if(target.matches('.remove')){
        console.log(target);
		//Remove node clicked
		removeNode(target);
	}else{
		return;
	}

	event.stopImmediatePropagation();
});
