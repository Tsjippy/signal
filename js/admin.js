import {copyFormInput, fixNumbering, removeNode} from '../../forms/js/forms.js';

document.addEventListener('click', function(event) {
	let target = event.target;
	
	//add element
	if(target.matches('.add')){
		copyFormInput(target.closest(".clone_div"));

		fixNumbering(target.closest('.clone_divs_wrapper'));

		target.remove();
	}
	
	//remove element
	if(target.matches('.remove')){
        console.log(target);
		//Remove node clicked
		removeNode(target);
	}
});