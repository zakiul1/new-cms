// resources/js/cms/wp-media-modal.js
document.addEventListener("alpine:init", () => {
    Alpine.store("wpMediaModal", {
        openId: null,

        open(id) {
            this.openId = id;
            document.documentElement.classList.add("overflow-hidden");
        },

        close() {
            this.openId = null;
            document.documentElement.classList.remove("overflow-hidden");
        },

        isOpen(id) {
            return this.openId === id;
        },
    });
});
