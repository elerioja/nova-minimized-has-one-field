Nova.booting((Vue, router, store) => {
  Vue.component(
    "index-nova-hasone-field-minimizer",
    require("./components/IndexField")
  );
  Vue.component(
    "detail-nova-hasone-field-minimizer",
    require("./components/DetailField")
  );
  Vue.component(
    "form-nova-hasone-field-minimizer",
    require("./components/FormField")
  );

  console.log("nova-panel", document.getElementsByClassName("nova-panel"));
  console.log(
    "nova-relationshi-panel",
    document.getElementsByClassName("nova-relationship-panel")
  );
});
