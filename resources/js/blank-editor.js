import { Editor, Extension, Node } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import TextAlign from '@tiptap/extension-text-align';
import TextStyle from '@tiptap/extension-text-style';
import Color from '@tiptap/extension-color';
import FontFamily from '@tiptap/extension-font-family';
import Highlight from '@tiptap/extension-highlight';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';

/**
 * Таблица без границ — способ собрать шапку бланка: логотип в левой ячейке, реквизиты в правой,
 * рамок не видно. Признак держим классом: inline-стиль border: none санитайзер бы срезал.
 */
const BorderlessTable = Table.extend({
    addAttributes() {
        return {
            ...this.parent?.(),

            borderless: {
                default: false,
                parseHTML: (el) => el.classList.contains('table-borderless'),
                renderHTML: (attrs) => (attrs.borderless ? { class: 'table-borderless' } : {}),
            },
        };
    },

    addCommands() {
        return {
            ...this.parent?.(),

            toggleTableBorders: () => ({ editor, commands }) =>
                commands.updateAttributes('table', {
                    borderless: !editor.getAttributes('table').borderless,
                }),
        };
    },
});

/** Ячейка запоминает, прижато ли её содержимое к верху, середине или низу. */
const alignedCell = (Base) => Base.extend({
    addAttributes() {
        return {
            ...this.parent?.(),

            verticalAlign: {
                default: null,
                parseHTML: (el) => el.style.verticalAlign || null,
                renderHTML: (attrs) => (attrs.verticalAlign ? { style: `vertical-align: ${attrs.verticalAlign}` } : {}),
            },
        };
    },
});

const AlignedTableCell   = alignedCell(TableCell);
const AlignedTableHeader = alignedCell(TableHeader);

/** Размер шрифта — обычный inline-стиль поверх textStyle, своего расширения в TipTap нет. */
const FontSize = Extension.create({
    name: 'fontSize',

    addGlobalAttributes() {
        return [{
            types: ['textStyle'],
            attributes: {
                fontSize: {
                    default: null,
                    parseHTML: (el) => el.style.fontSize || null,
                    renderHTML: (attrs) => (attrs.fontSize ? { style: `font-size: ${attrs.fontSize}` } : {}),
                },
            },
        }];
    },

    addCommands() {
        return {
            setFontSize: (size) => ({ chain }) => chain().setMark('textStyle', { fontSize: size }).run(),
        };
    },
});

/** Разрыв страницы: в редакторе — пунктир, при печати — реальный обрыв листа. */
const PageBreak = Node.create({
    name: 'pageBreak',
    group: 'block',
    atom: true,
    selectable: true,

    parseHTML() {
        return [{ tag: 'div.page-break' }];
    },

    renderHTML() {
        return ['div', { class: 'page-break' }];
    },

    addCommands() {
        return {
            setPageBreak: () => ({ commands }) => commands.insertContent({ type: 'pageBreak' }),
        };
    },
});

/**
 * Токен — цельный неделимый узел, а не просто текст «{номер}». Курсор его не разрезает,
 * а на выходе он остаётся обычным «{номер}» внутри span, так что подстановку делает
 * тот же DocumentNamingService, что и в маске названия.
 */
const Token = Node.create({
    name: 'token',
    group: 'inline',
    inline: true,
    atom: true,

    addAttributes() {
        return {
            key: {
                default: '',
                parseHTML: (el) => el.textContent.replace(/[{}]/g, '').trim(),
                renderHTML: () => ({}),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'span.doc-token' }];
    },

    renderHTML({ node }) {
        return ['span', { class: 'doc-token' }, `{${node.attrs.key}}`];
    },

    renderText({ node }) {
        return `{${node.attrs.key}}`;
    },
});

/**
 * Положение картинки. Первые три — картинка занимает строку целиком; float-* — текст обтекает
 * её сбоку, и тогда логотип со всеми реквизитами справа собирается без всякой таблицы.
 * Всё это классы, а не inline-стиль: HTMLPurifier не знает свойств display и float и вырезал бы их.
 */
const IMAGE_CLASS = {
    left:          'img-align-left',
    center:        'img-align-center',
    right:         'img-align-right',
    'float-left':  'img-float-left',
    'float-right': 'img-float-right',
};

const imageAlignOf = (el) => Object.keys(IMAGE_CLASS)
    .find((align) => el.classList.contains(IMAGE_CLASS[align])) ?? 'left';

/**
 * Картинка как полноценный объект: её можно выделить, растянуть за угол и выровнять.
 * Штатное расширение отдаёт голый <img>, поэтому подменяем ему представление в редакторе.
 */
const ResizableImage = Image.extend({
    addAttributes() {
        return {
            ...this.parent?.(),

            // Ширину и выравнивание в HTML пишет renderHTML самого узла (ниже): они оба ложатся
            // в один элемент, и по отдельности затирали бы друг другу class и style.
            width: {
                default: null,
                parseHTML: (el) => el.style.width || el.getAttribute('width'),
                renderHTML: () => ({}),
            },

            align: {
                default: 'left',
                parseHTML: imageAlignOf,
                renderHTML: () => ({}),
            },
        };
    },

    // Берём node.attrs, а не HTMLAttributes: во втором лежат уже отрендеренные атрибуты,
    // где width и align как таковых нет.
    renderHTML({ node, HTMLAttributes }) {
        const { width, align } = node.attrs;
        const { style: _style, class: _class, ...rest } = HTMLAttributes;

        return ['img', {
            ...rest,
            class: IMAGE_CLASS[align] ?? IMAGE_CLASS.left,
            style: width ? `width: ${typeof width === 'number' ? `${width}px` : width}` : null,
        }];
    },

    addNodeView() {
        return ({ node, editor, getPos }) => {
            const wrapper = document.createElement('div');
            const img = document.createElement('img');

            const applyAttrs = (attrs) => {
                wrapper.className = `img-wrap ${IMAGE_CLASS[attrs.align] ?? IMAGE_CLASS.left}`;
                img.src = attrs.src;
                img.alt = attrs.alt ?? '';
                img.style.width = attrs.width ?? '';
            };

            applyAttrs(node.attrs);
            wrapper.appendChild(img);

            // Тянем за любой из четырёх углов; пропорции сохраняем — высота идёт следом за шириной.
            ['nw', 'ne', 'sw', 'se'].forEach((corner) => {
                const handle = document.createElement('span');
                handle.className = `img-handle img-handle-${corner}`;

                handle.addEventListener('pointerdown', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const startX     = event.clientX;
                    const startWidth = img.getBoundingClientRect().width;
                    const growsRight = corner === 'ne' || corner === 'se';
                    const maxWidth   = wrapper.parentElement?.clientWidth ?? 800;

                    const onMove = (move) => {
                        const delta = (move.clientX - startX) * (growsRight ? 1 : -1);
                        const width = Math.min(Math.max(startWidth + delta, 40), maxWidth);
                        img.style.width = `${Math.round(width)}px`;
                    };

                    const onUp = () => {
                        document.removeEventListener('pointermove', onMove);
                        document.removeEventListener('pointerup', onUp);

                        // Ширину в документ пишем один раз — в конце, а не на каждом кадре мыши,
                        // иначе история отмены забьётся сотней шагов.
                        if (typeof getPos === 'function') {
                            const pos = getPos();
                            const current = editor.state.doc.nodeAt(pos);

                            if (current) {
                                editor.view.dispatch(editor.state.tr.setNodeMarkup(pos, undefined, {
                                    ...current.attrs,
                                    width: img.style.width,
                                }));
                            }
                        }
                    };

                    document.addEventListener('pointermove', onMove);
                    document.addEventListener('pointerup', onUp);
                });

                wrapper.appendChild(handle);
            });

            return {
                dom: wrapper,

                update(updated) {
                    if (updated.type.name !== 'image') {
                        return false;
                    }

                    applyAttrs(updated.attrs);

                    return true;
                },

                selectNode: () => wrapper.classList.add('img-selected'),
                deselectNode: () => wrapper.classList.remove('img-selected'),
            };
        };
    },
});

/** Набор расширений вынесен, чтобы тест round-trip гонял ровно ту же схему, что и редактор. */
export const blankExtensions = [
    StarterKit,
    Underline,
    TextStyle,
    Color,
    FontFamily,
    FontSize,
    Highlight.configure({ multicolor: true }),
    TextAlign.configure({ types: ['heading', 'paragraph'] }),
    Link.configure({ openOnClick: false }),
    // allowBase64 по умолчанию false — тогда правило разбора исключает src="data:...", и
    // вставленный логотип при следующем открытии бланка просто исчезал бы.
    ResizableImage.configure({ inline: false, allowBase64: true }),
    BorderlessTable.configure({ resizable: true }),
    TableRow,
    AlignedTableHeader,
    AlignedTableCell,
    PageBreak,
    Token,
];

export function blankEditor({ content = '' } = {}) {
    // Редактор держим вне реактивных данных Alpine: прокси Alpine ломает внутреннее
    // состояние ProseMirror.
    let editor = null;

    return {
        html: content,
        tick: 0, // счётчик, который делает состояние кнопок панели реактивным

        init() {
            editor = new Editor({
                element: this.$refs.editor,
                content: content || '<p></p>',
                extensions: blankExtensions,
                editorProps: {
                    // blank-editing — только для редактора: по нему рисуем служебные подсказки
                    // (пунктир у таблицы без границ), которых в готовом документе быть не должно.
                    attributes: { class: 'blank-sheet blank-editing focus:outline-none' },
                },
                onUpdate: ({ editor }) => {
                    this.html = editor.getHTML();
                },
                onTransaction: () => {
                    this.tick++;
                },
            });
        },

        destroy() {
            editor?.destroy();
        },

        /** Любая команда TipTap: run('toggleBold'), run('setTextAlign', 'center'). */
        run(command, ...args) {
            editor?.chain().focus()[command](...args).run();
        },

        isActive(name, attrs = {}) {
            this.tick; // читаем счётчик — иначе Alpine не пересчитает класс кнопки
            return editor?.isActive(name, attrs) ?? false;
        },

        insertToken(key) {
            if (!key) return;
            editor?.chain().focus().insertContent({ type: 'token', attrs: { key } }).run();
        },

        setFontSize(size) {
            if (size) editor?.chain().focus().setFontSize(size).run();
        },

        setFontFamily(family) {
            const chain = editor?.chain().focus();
            family ? chain.setFontFamily(family).run() : chain.unsetFontFamily().run();
        },

        setColor(color) {
            editor?.chain().focus().setColor(color).run();
        },

        insertTable() {
            editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
        },

        inTable() {
            this.tick;
            return editor?.isActive('table') ?? false;
        },

        borderlessTable() {
            this.tick;
            return editor?.getAttributes('table').borderless ?? false;
        },

        setCellAlign(align) {
            editor?.chain().focus().setCellAttribute('verticalAlign', align).run();
        },

        /**
         * Шапка бланка: логотип слева, реквизиты справа, рамок нет. Ровно то, что в Word
         * собирают таблицей 2×1 — руками это десяток кликов, поэтому даём одной кнопкой.
         */
        insertLetterhead() {
            editor?.chain().focus().insertContent(`
                <table class="table-borderless">
                    <tbody>
                        <tr>
                            <td style="width: 40%; vertical-align: middle"><p></p></td>
                            <td style="width: 60%; vertical-align: middle">
                                <p style="text-align: right"><strong>ООО «Название»</strong></p>
                                <p style="text-align: right">Адрес</p>
                                <p style="text-align: right">Тел.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p></p>
            `).run();
        },

        addLink() {
            const url = window.prompt('Адрес ссылки', editor?.getAttributes('link').href ?? 'https://');
            if (url === null) return;

            url === ''
                ? editor?.chain().focus().unsetLink().run()
                : editor?.chain().focus().setLink({ href: url }).run();
        },

        imageSelected() {
            this.tick;
            return editor?.isActive('image') ?? false;
        },

        imageAlign() {
            this.tick;
            return editor?.getAttributes('image').align ?? 'left';
        },

        setImageAlign(align) {
            editor?.chain().focus().updateAttributes('image', { align }).run();
        },

        /** Доля от ширины листа: проценты переживают и печать, и выгрузку. */
        setImageWidth(width) {
            editor?.chain().focus().updateAttributes('image', { width }).run();
        },

        deleteImage() {
            editor?.chain().focus().deleteSelection().run();
        },

        /** Картинку кладём прямо в шаблон как data-URI — отдельного хранилища для неё не заводим. */
        pickImage(event) {
            const file = event.target.files?.[0];
            event.target.value = '';

            if (!file) return;

            if (file.size > 2 * 1024 * 1024) {
                window.alert('Картинка больше 2 МБ — уменьшите её, иначе шаблон станет неподъёмным.');
                return;
            }

            const reader = new FileReader();
            reader.onload = () => editor?.chain().focus().setImage({ src: reader.result }).run();
            reader.readAsDataURL(file);
        },
    };
}
