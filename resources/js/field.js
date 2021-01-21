import IndexField from "./components/IndexField";
import FormField from "./components/FormField";
import DetailField from "./components/DetailField";

Nova.booting((Vue) => {
  Vue.component("index-nova-hasone-field-minimizer", IndexField);
  Vue.component("detail-nova-hasone-field-minimizer", DetailField);
  Vue.component("form-nova-hasone-field-minimizer", FormField);
});
