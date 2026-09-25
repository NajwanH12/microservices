
const productModel = require('../models/productModel');

// GET ambil semua products
async function index(req, res) {
    try {
        const products = await productModel.getAllProducts();
        res.json({ message: "Berhasil mengambil data produk", data: products });
    } catch (error) {
        res.status(500).json({ message: "Gagal mengambil data produk", error: error.message });
    }
}

module.exports = { index };
