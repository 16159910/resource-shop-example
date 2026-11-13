/* BizSelect — 后台自定义下拉组件 */
window.BizSelect = {
  props: {
    modelValue: { default: '' },
    options:     { type: Array,   default: () => [] }, // [{value, label}]
    placeholder: { type: String,  default: '请选择' },
    full:        { type: Boolean, default: false }      // 是否撑满父容器宽度
  },
  emits: ['update:modelValue', 'change'],
  template: `
    <div class="biz-sel" :class="{ 'biz-sel--open': open, 'biz-sel--full': full, 'biz-sel--up': up }" ref="root">
      <div class="biz-sel-trigger" @click.stop="toggle">
        <span :class="selectedLabel ? 'biz-sel-val' : 'biz-sel-ph'">{{ selectedLabel || placeholder }}</span>
        <i class="fa-solid fa-chevron-down biz-sel-arrow"></i>
      </div>
      <div class="biz-sel-drop" v-show="open" @click.stop>
        <div
          v-for="opt in options"
          :key="opt.value"
          class="biz-sel-opt"
          :class="{ active: String(opt.value) == String(modelValue) }"
          @click="pick(opt)"
        >
          <span>{{ opt.label }}</span>
          <i class="fa-solid fa-check biz-sel-check" v-if="String(opt.value) == String(modelValue)"></i>
        </div>
      </div>
    </div>
  `,
  data() { return { open: false, up: false }; },
  computed: {
    selectedLabel() {
      const opt = this.options.find(o => String(o.value) == String(this.modelValue));
      return opt ? opt.label : '';
    }
  },
  methods: {
    toggle() {
      const next = !this.open;
      // trigger 上有 @click.stop，事件到不了 document，
      // 别的下拉的「点外面关闭」就失效了 —— 所以打开时主动广播一次，
      // 让其它下拉（含日期选择器）自己收起，保证同时只开一个
      if (next) {
        document.dispatchEvent(new CustomEvent('biz-dropdown-open', { detail: this }));
        this.up = this.shouldOpenUp();
      }
      this.open = next;
    },
    /**
     * 下方放不下就往上弹。弹窗主体是滚动容器（overflow:auto），放在底部的下拉
     * 往下展开会被裁掉，所以要按最近的滚动容器（没有就按窗口）算剩余空间。
     */
    shouldOpenUp() {
      const root = this.$refs.root;
      if (!root) return false;
      const rect = root.getBoundingClientRect();
      let box = { top: 0, bottom: window.innerHeight };
      for (let el = root.parentElement; el && el !== document.body; el = el.parentElement) {
        const oy = getComputedStyle(el).overflowY;
        if (oy === 'auto' || oy === 'scroll' || oy === 'hidden') { box = el.getBoundingClientRect(); break; }
      }
      const need = Math.min(this.options.length * 40 + 12, 280) + 8; // 和 .biz-sel-drop 的 max-height 一致
      const below = box.bottom - rect.bottom;
      return below < need && rect.top - box.top > below;
    },
    onOtherOpen(e) { if (e.detail !== this) this.open = false; },
    pick(opt) {
      this.$emit('update:modelValue', opt.value);
      this.$emit('change', opt.value);
      this.open = false;
    },
    onOutside(e) {
      if (this.$refs.root && !this.$refs.root.contains(e.target)) {
        this.open = false;
      }
    }
  },
  mounted() {
    document.addEventListener('click', this.onOutside);
    document.addEventListener('biz-dropdown-open', this.onOtherOpen);
  },
  beforeUnmount() {
    document.removeEventListener('click', this.onOutside);
    document.removeEventListener('biz-dropdown-open', this.onOtherOpen);
  }
};
