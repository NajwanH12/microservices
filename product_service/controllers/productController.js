const productModel = require('../models/productModel');

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
    const { name, description, price, stock } = req.body;
    if (!name || price === undefined || stock === undefined) {
        return res.status(400).json({ message: 'name, price, dan stock wajib diisi' });
    }
    try {
        const id = await productModel.createProduct({ name, description, price, stock });
        const product = await productModel.getProductById(id);
        res.status(201).json({ message: 'Produk berhasil dibuat', data: product });
    } catch (error) {
        sendError(res, 'Gagal membuat produk', error);
    }
}

async function update(req, res) {
    const { name, description, price, stock } = req.body;
    if (!name || price === undefined || stock === undefined) {
        return res.status(400).json({ message: 'name, price, dan stock wajib diisi' });
    }
    try {
        const product = await productModel.updateProduct(req.params.id, { name, description, price, stock });
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

module.exports = { index, show, create, update, destroy };
