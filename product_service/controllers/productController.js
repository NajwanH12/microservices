const productModel = require('../models/productModel');

const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
const BASE64_PATTERN = /^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/;

function validateImage(image) {
    if (image === undefined || image === null || image === '') {
        return 'Field image wajib diisi';
    }
    if (typeof image !== 'string' || !BASE64_PATTERN.test(image)) {
        return 'Field image harus berupa Base64 yang valid';
    }

    const decoded = Buffer.from(image, 'base64');
    if (decoded.toString('base64') !== image) {
        return 'Field image harus berupa Base64 yang valid';
    }
    if (decoded.length > MAX_IMAGE_BYTES) {
        return 'Ukuran image maksimal 2 MB';
    }
    return null;
}

function sendError(res, message, error) {
    res.status(500).json({ message, error: error.message });
}

async function index(req, res) {
    try {
        const products = await productModel.getAllProducts();
        res.json({ message: 'Berhasil mengambil data produk', data: products });
    } catch (error) {
        sendError(res, 'Gagal mengambil data produk', error);
    }
}

async function show(req, res) {
    try {
        const product = await productModel.getProductById(req.params.id);
        if (!product) return res.status(404).json({ message: 'Produk tidak ditemukan' });
        res.json({ message: 'Berhasil mengambil data produk', data: product });
    } catch (error) {
        sendError(res, 'Gagal mengambil data produk', error);
    }
}

async function create(req, res) {
    const { name, description, price, stock, image } = req.body || {};
    const imageError = validateImage(image);
    if (imageError) return res.status(400).json({ message: imageError });
    if (!name || price === undefined || stock === undefined) {
        return res.status(400).json({ message: 'name, price, dan stock wajib diisi' });
    }
    try {
        const id = await productModel.createProduct({ name, description, price, stock, image });
        const product = await productModel.getProductById(id);
        res.status(201).json({ message: 'Produk berhasil dibuat', data: product });
    } catch (error) {
        sendError(res, 'Gagal membuat produk', error);
    }
}

async function update(req, res) {
    const { name, description, price, stock, image } = req.body || {};
    const imageError = validateImage(image);
    if (imageError) return res.status(400).json({ message: imageError });
    if (!name || price === undefined || stock === undefined) {
        return res.status(400).json({ message: 'name, price, dan stock wajib diisi' });
    }
    try {
        const product = await productModel.updateProduct(req.params.id, { name, description, price, stock, image });
        if (!product) return res.status(404).json({ message: 'Produk tidak ditemukan' });
        res.json({ message: 'Produk berhasil diperbarui', data: product });
    } catch (error) {
        sendError(res, 'Gagal memperbarui produk', error);
    }
}

async function destroy(req, res) {
    try {
        const deleted = await productModel.deleteProduct(req.params.id);
        if (!deleted) return res.status(404).json({ message: 'Produk tidak ditemukan' });
        res.json({ message: 'Produk berhasil dihapus' });
    } catch (error) {
        sendError(res, 'Gagal menghapus produk', error);
    }
}

module.exports = { index, show, create, update, destroy, validateImage };
