import {
  copyFormInput,
  fixNumbering,
  removeNode,
} from "@tsjippy/form_exports";

document.addEventListener("click", function (event) {
  let target = event.target;

  //add element
  if (target.matches(".add")) {
    copyFormInput(target.closest(".clone-div"));

    fixNumbering(target.closest(".clone-divs-wrapper"));

    target.remove();
  }

  //remove element
  else if (target.matches(".remove")) {
    //Remove node clicked
    removeNode(target);
  }

  else if (target.matches(".expand")) {
    let rowspan = target.closest("td").dataset.rowspan;

    target.closest("td").rowSpan = rowspan;

    let row = target.closest("tr").nextElementSibling;

    while (row.matches(".hidden")) {
      row.classList.remove("hidden");
      row = row.nextElementSibling;

      if (row == null) {
        break;
      }
    }

    target.textContent = "-";
    target.classList.replace("expand", "condense");
  } else if (target.matches(".condense")) {
    let rowspan = (target.closest("td").rowSpan = 1);

    let row = target.closest("tr").nextElementSibling;

    while (row.querySelector("td.chat") == null) {
      row.classList.add("hidden");
      row = row.nextElementSibling;

      if (row == null) {
        break;
      }
    }

    target.textContent = "+";
    target.classList.replace("condense", "expand");
  } else {
    return;
  }

  ev.stopImmediatePropagation();
});

document.addEventListener("emoji_selected", function (ev) {
  ev.target.closest("form").submit();
});
