document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('foto-animal');
    const preview = document.getElementById('foto-animal-preview');
    const image = document.getElementById('foto-animal-preview-img');
    const label = document.getElementById('foto-animal-preview-label');

    if (!(input instanceof HTMLInputElement)
        || !(preview instanceof HTMLDivElement)
        || !(image instanceof HTMLImageElement)
        || !(label instanceof HTMLSpanElement)) {
        return;
    }

    let previewUrl = '';
    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
            return;
        }

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }
        previewUrl = URL.createObjectURL(file);
        image.src = previewUrl;
        label.textContent = `Prévia da foto selecionada: ${file.name}`;
        preview.hidden = false;
    });
});
