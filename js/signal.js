
import { 
  displayMessage 
} from "../../tsjippy-shared-functionality/js/partials/display_message.js";


document.addEventListener("click", async (ev) => {
  let target = ev.target;

  if (target.name == "save_signal_preferences") {
    ev.stopImmediatePropagation();

    let response = await submitForm(
      target,
      "signal/save_preferences",
    );
    if (response) {
      displayMessage(response);
    }
  }
});
