import { 
  displayMessage 
} from "@tsjippy/display_message";

import{
  submitForm
} from "@tsjippy/form_submit_functions";

document.addEventListener("click", async (ev) => {
  let target = ev.target;

  if (target.name == "save_signal_preferences") {
    ev.stopPropagation();

    let response = await submitForm(
      target,
      "signal/save_preferences",
    );
    if (response) {
      displayMessage(response);
    }
  }
});
