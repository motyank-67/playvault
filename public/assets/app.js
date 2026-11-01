document.querySelectorAll("[data-confirm-delete]").forEach((form) => {
  form.addEventListener("submit", (event) => {
    if (!window.confirm("Точно удалить эту игру из коллекции?")) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll(".edit-details").forEach((details) => {
  details.addEventListener("toggle", () => {
    if (!details.open) return;
    document.querySelectorAll(".edit-details").forEach((other) => {
      if (other !== details) other.open = false;
    });
  });
});
