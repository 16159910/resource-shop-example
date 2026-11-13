const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const root = path.resolve(__dirname, "..");
const compileOptions = {
  delimiters: ["[[", "]]"],
  // This test runs without a browser; decode the standard entities in attributes.
  decodeEntities(text) {
    const named = { amp: "&", lt: "<", gt: ">", quot: '"', apos: "'" };
    return text.replace(/&(#x[0-9a-f]+|#\d+|amp|lt|gt|quot|apos);/gi, (_, value) => {
      if (value[0] !== "#") return named[value.toLowerCase()];
      const hex = value[1].toLowerCase() === "x";
      return String.fromCodePoint(parseInt(value.slice(hex ? 2 : 1), hex ? 16 : 10));
    });
  },
};
const context = { console };
vm.createContext(context);
vm.runInContext(
  fs.readFileSync(path.join(root, "public/static/lib/vue/vue.global.prod.min.js"), "utf8"),
  context,
);

// An array's closing bracket must remain separate from the custom ]] delimiter.
assert.throws(
  () => context.Vue.compile("<div>[[field[1]]]</div>", compileOptions),
  /Unexpected token/,
);

const examples = {
  "config/site.html": {
    cfg: { site_name: "示例站", default_wechat: "", default_wechat_qr: "", watermark_text: "示例", watermark_opacity: "35", wecom_webhook: "" },
    baseFields: [["site_name", "网站名称", "示例站"]],
    mailFields: [["smtp_host", "SMTP 服务器"]],
    saving: false,
    save() {},
  },
  "config/payment.html": {
    channels: [{ name: "支付宝", enabled: false, values: {}, fields: [["app_id", "应用 App ID"], ["private_key", "应用私钥", "textarea"]] }],
    save() {},
  },
  "user/index.html": {
    keyword: "", page: 1, total: 0, list: [], show: false, form: {},
    edit() {}, search() {}, load() {}, save() {},
  },
  "order/index.html": {
    keyword: "", status: "", page: 1, total: 0, list: [],
    search() {}, load() {},
  },
};

for (const [name, scope] of Object.entries(examples)) {
  const source = fs.readFileSync(path.join(root, "app/admin/view", name), "utf8");
  const content = source.match(/\{block name="content"\}([\s\S]*?)\{\/block\}/);
  assert.ok(content, `${name}: content block is present`);
  try {
    const render = context.Vue.compile(content[1], compileOptions);
    assert.ok(render(scope, []), `${name}: initial render produces a VNode`);
    console.log(`${name}: template compile and initial render passed`);
  } catch (error) {
    console.error(`${name}: ${error.name}: ${error.message}`);
    process.exit(1);
  }
}
