import{
  fetchRestApi
} from "../../tsjippy-forms/js/form_submit_functions.js";


document.addEventListener("click", async (ev) => {
  let target = ev.target;

  if (target.name == "save_signal_preferences") {
    ev.stopImmediatePropagation();

    let response = await submitForm(
      target,
      "signal/save_preferences",
    );
    if (response) {
      Main.displayMessage(response);
    }
  }
});
