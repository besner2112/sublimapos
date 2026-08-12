// ==========================================================================
// Módulo POS (Ventas Rápidas, Carrito y AJAX)
// ==========================================================================

let cart = [];
let selectedCustomer = { id: 1, nombre: "Público General" }; // Cliente predeterminado
let currentGrandTotal = 0.00; // Variable global numérica para evitar errores de parseo en el cambio

// Inicialización del POS
document.addEventListener('DOMContentLoaded', () => {
    // Foco inicial en buscador de código de barras
    const barcodeInput = document.getElementById('barcode-search');
    if (barcodeInput) barcodeInput.focus();

    // Buscador por Nombre (autocompletar)
    const productSearchInput = document.getElementById('product-name-search');
    if (productSearchInput) {
        productSearchInput.addEventListener('input', debounce(searchProductsByName, 300));
    }

    // Buscador por Código de Barras (al presionar Enter)
    if (barcodeInput) {
        barcodeInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchAndAddByBarcode(barcodeInput.value.trim());
                barcodeInput.value = '';
            }
        });
    }

    // Buscador de Clientes (autocompletar)
    const customerSearchInput = document.getElementById('customer-search');
    if (customerSearchInput) {
        customerSearchInput.addEventListener('input', debounce(searchCustomer, 300));
    }

    // Calcular cambio en tiempo real al cobrar
    const cashReceivedInput = document.getElementById('cash-received');
    if (cashReceivedInput) {
        cashReceivedInput.addEventListener('input', calculateChange);
    }

    // Selección de método de pago cambia la interfaz
    const paymentMethodSelect = document.getElementById('payment-method');
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', togglePaymentDetails);
    }

    // Confirmación final del cobro
    const checkoutConfirmBtn = document.getElementById('btn-confirm-checkout');
    if (checkoutConfirmBtn) {
        checkoutConfirmBtn.addEventListener('click', submitSalesCheckout);
    }
});

// ==========================================
// FUNCIONES AJAX Y DE BÚSQUEDA
// ==========================================

/**
 * Función útil para retrasar la búsqueda (Debounce).
 */
function debounce(func, delay) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
}

/**
 * Busca productos por nombre en la base de datos vía AJAX.
 */
function searchProductsByName() {
    const q = document.getElementById('product-name-search').value.trim();
    const dropdown = document.getElementById('product-search-results');
    
    if (q.length < 2) {
        dropdown.innerHTML = '';
        dropdown.classList.remove('show');
        return;
    }

    fetch(`index.php?route=buscar_productos_ajax&q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(products => {
            dropdown.innerHTML = '';
            if (products.length === 0) {
                dropdown.innerHTML = '<li class="dropdown-item text-muted">Ningún producto encontrado</li>';
            } else {
                products.forEach(p => {
                    const li = document.createElement('li');
                    li.className = 'dropdown-item d-flex justify-content-between align-items-center py-2';
                    li.style.cursor = 'pointer';
                    li.innerHTML = `
                        <div>
                            <strong>${p.nombre}</strong><br>
                            <small class="text-secondary">Cod: ${p.codigo_barras} | Stock: ${p.stock}</small>
                        </div>
                        <span class="badge bg-cyan text-dark fw-bold">${formatMoney(p.precio_venta)}</span>
                    `;
                    li.addEventListener('click', () => {
                        addToCart(p);
                        dropdown.innerHTML = '';
                        dropdown.classList.remove('show');
                        document.getElementById('product-name-search').value = '';
                    });
                    dropdown.appendChild(li);
                });
            }
            dropdown.classList.add('show');
        })
        .catch(err => {
            console.error("Error al buscar productos por nombre:", err);
        });
}

/**
 * Escanea y agrega directamente por código de barras.
 */
function searchAndAddByBarcode(code) {
    if (!code) return;

    fetch(`index.php?route=buscar_codigo_ajax&code=${encodeURIComponent(code)}`)
        .then(res => res.json())
        .then(product => {
            if (product && product.id) {
                addToCart(product);
            } else {
                showToast("Producto No Encontrado", "El código ingresado no coincide con ningún artículo en stock.", "danger");
            }
        })
        .catch(err => {
            console.error("Error al buscar por código de barras:", err);
            showToast("Error", "Error al escanear el código.", "danger");
        });
}

/**
 * Busca clientes para asignar a la factura.
 */
function searchCustomer() {
    const q = document.getElementById('customer-search').value.trim();
    const dropdown = document.getElementById('customer-search-results');

    if (q.length < 2) {
        dropdown.innerHTML = '';
        dropdown.classList.remove('show');
        return;
    }

    fetch(`index.php?route=buscar_clientes_ajax&q=${encodeURIComponent(q)}`)
        .then(res => res.json())
        .then(customers => {
            dropdown.innerHTML = '';
            if (customers.length === 0) {
                dropdown.innerHTML = '<li class="dropdown-item text-muted">Ningún cliente encontrado</li>';
            } else {
                customers.forEach(c => {
                    const li = document.createElement('li');
                    li.className = 'dropdown-item py-2';
                    li.style.cursor = 'pointer';
                    li.innerHTML = `
                        <strong>${c.nombre}</strong><br>
                        <small class="text-secondary">RFC/DNI: ${c.identificacion || 'N/D'} | Tel: ${c.telefono || 'N/D'}</small>
                    `;
                    li.addEventListener('click', () => {
                        assignCustomer(c);
                        dropdown.innerHTML = '';
                        dropdown.classList.remove('show');
                        document.getElementById('customer-search').value = '';
                    });
                    dropdown.appendChild(li);
                });
            }
            dropdown.classList.add('show');
        })
        .catch(err => {
            console.error("Error al buscar clientes:", err);
        });
}

/**
 * Vincula el cliente seleccionado a la compra actual.
 */
function assignCustomer(c) {
    selectedCustomer = { id: c.id, nombre: c.nombre };
    document.getElementById('customer-selected-badge').innerHTML = `
        <span class="badge bg-secondary p-2 me-1">${c.nombre}</span>
        <button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="clearCustomer()">&times;</button>
    `;
    showToast("Cliente Asignado", `Venta asociada a: ${c.nombre}`, "success");
}

/**
 * Vuelve la asociación de factura a "Público General".
 */
function clearCustomer() {
    selectedCustomer = { id: 1, nombre: "Público General" };
    document.getElementById('customer-selected-badge').innerHTML = '<span class="badge bg-dark p-2 text-secondary">Público General</span>';
}

// ==========================================
// LOGICA INTERNA DEL CARRITO DE COMPRAS
// ==========================================

function addToCart(p) {
    // Si el producto está agotado
    if (p.stock <= 0) {
        showToast("Producto Agotado", `El artículo '${p.nombre}' no tiene stock disponible.`, "warning");
        return;
    }

    const index = cart.findIndex(item => item.id === p.id);

    if (index !== -1) {
        // Validar si excede el stock actual
        if (cart[index].cantidad + 1 > p.stock) {
            showToast("Límite de Stock", `Solo hay ${p.stock} unidades de '${p.nombre}' en inventario.`, "warning");
            return;
        }
        cart[index].cantidad++;
    } else {
        cart.push({
            id: p.id,
            nombre: p.nombre,
            codigo_barras: p.codigo_barras,
            precio_venta: parseFloat(p.precio_venta),
            stock: parseInt(p.stock),
            cantidad: 1
        });
    }

    updateCartUI();
}

function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    updateCartUI();
}

function updateQuantity(id, newQty) {
    const item = cart.find(item => item.id === id);
    if (!item) return;

    newQty = parseInt(newQty);
    if (isNaN(newQty) || newQty <= 0) {
        removeFromCart(id);
        return;
    }

    if (newQty > item.stock) {
        showToast("Límite de Stock", `Solo hay ${item.stock} unidades de este producto en el catálogo.`, "warning");
        item.cantidad = item.stock;
    } else {
        item.cantidad = newQty;
    }

    updateCartUI();
}

/**
 * Actualiza la información y el desglose de totales en el POS.
 */
function updateCartUI() {
    const container = document.getElementById('cart-items-container');
    if (!container) return;

    container.innerHTML = '';

    if (cart.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted p-5">
                <i class="bi bi-cart3 fs-1 d-block mb-3" style="color: var(--border-color);"></i>
                El carrito está vacío
            </div>`;
        document.getElementById('subtotal-display').innerText = '$0.00';
        document.getElementById('tax-display').innerText = '$0.00';
        document.getElementById('total-display').innerText = '$0.00';
        document.getElementById('pos-checkout-btn').disabled = true;
        currentGrandTotal = 0.00;
        return;
    }

    let grandTotal = 0.00;

    cart.forEach(item => {
        const itemTotal = item.precio_venta * item.cantidad;
        grandTotal += itemTotal;

        const row = document.createElement('div');
        row.className = 'cart-item-row d-flex justify-content-between align-items-center';
        row.innerHTML = `
            <div style="max-width: 50%;">
                <div class="fw-bold text-truncate" title="${item.nombre}">${item.nombre}</div>
                <small class="text-secondary">${formatMoney(item.precio_venta)} c/u</small>
            </div>
            <div class="d-flex align-items-center" style="max-width: 35%;">
                <button class="btn btn-outline-cyan btn-sm py-0 px-2 fw-bold" onclick="updateQuantity(${item.id}, ${item.cantidad - 1})">-</button>
                <input type="number" class="form-control form-control-custom text-center mx-1 py-0 px-1" value="${item.cantidad}" style="width: 50px; font-weight:bold;" onchange="updateQuantity(${item.id}, this.value)">
                <button class="btn btn-outline-cyan btn-sm py-0 px-2 fw-bold" onclick="updateQuantity(${item.id}, ${item.cantidad + 1})">+</button>
            </div>
            <div class="text-end" style="min-width: 15%;">
                <div class="fw-bold">${formatMoney(itemTotal)}</div>
                <button class="btn btn-link text-danger p-0 border-0" style="text-decoration:none;" onclick="removeFromCart(${item.id})">Quitar</button>
            </div>
        `;
        container.appendChild(row);
    });

    // Guardar el total exacto en la variable global numérica
    currentGrandTotal = grandTotal;

    // Desglose de impuesto (IVA 16% incluido en el precio de venta)
    const subtotal = grandTotal / 1.16;
    const tax = grandTotal - subtotal;

    document.getElementById('subtotal-display').innerText = formatMoney(subtotal);
    document.getElementById('tax-display').innerText = formatMoney(tax);
    document.getElementById('total-display').innerText = formatMoney(grandTotal);
    document.getElementById('pos-checkout-btn').disabled = false;

    // Actualizar montos en el modal de cobro
    const modalTotalText = document.getElementById('checkout-modal-total-text');
    if (modalTotalText) modalTotalText.innerText = formatMoney(grandTotal);

    const cashReceivedInput = document.getElementById('cash-received');
    if (cashReceivedInput) {
        cashReceivedInput.value = grandTotal.toFixed(2);
        calculateChange();
    }
}

// ==========================================
// MODAL DE COBRO Y ARQUEO
// ==========================================

function togglePaymentDetails() {
    const method = document.getElementById('payment-method').value;
    const cashSection = document.getElementById('cash-payment-details');
    
    if (method === 'Efectivo') {
        cashSection.style.display = 'block';
    } else {
        cashSection.style.display = 'none';
    }
}

function calculateChange() {
    const receivedVal = parseFloat(document.getElementById('cash-received').value);
    const changeInput = document.getElementById('cash-change');
    const warning = document.getElementById('change-warning-message');

    if (isNaN(receivedVal)) {
        changeInput.value = '$0.00';
        return;
    }

    // Cálculo directo y seguro usando la variable global numérica
    const change = receivedVal - currentGrandTotal;
    changeInput.value = formatMoney(change);

    if (change < 0) {
        changeInput.style.color = 'var(--danger-red)';
        if (warning) warning.style.display = 'block';
        document.getElementById('btn-confirm-checkout').disabled = true;
    } else {
        changeInput.style.color = 'var(--success-green)';
        if (warning) warning.style.display = 'none';
        document.getElementById('btn-confirm-checkout').disabled = false;
    }
}

/**
 * Realiza el envío del cobro final hacia el servidor por AJAX.
 */
function submitSalesCheckout() {
    const button = document.getElementById('btn-confirm-checkout');
    const paymentMethod = document.getElementById('payment-method').value;
    const cashReceived = parseFloat(document.getElementById('cash-received').value);

    if (paymentMethod === 'Efectivo' && (isNaN(cashReceived) || cashReceived < currentGrandTotal)) {
        showToast("Error de Pago", "El efectivo recibido es insuficiente para concretar la venta.", "warning");
        return;
    }

    // Deshabilitar botón para evitar doble envío
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';

    // Armar el payload de la transacción
    const payload = {
        cliente_id: selectedCustomer.id,
        metodo_pago: paymentMethod,
        monto_pagado: paymentMethod === 'Efectivo' ? cashReceived : currentGrandTotal,
        carrito: cart.map(item => ({ id: item.id, cantidad: item.cantidad })),
        csrf_token: window.CSRF_TOKEN || ''
    };

    fetch('index.php?route=cobrar_ajax', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            // Mostrar modal de éxito
            const modalElement = document.getElementById('checkoutModal');
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) modal.hide();

            // Mensaje de éxito completo
            showToast("Venta Exitosa", `Venta registrada correctamente. Código: ${res.factura}. Cambio: ${formatMoney(res.cambio)}`, "success");
            
            // Vaciar carrito
            cart = [];
            clearCustomer();
            updateCartUI();

            // Abrir ticket informativo ficticio o recargar listados
            setTimeout(() => {
                location.reload(); // Recargar página para refrescar stock en listados inferiores
            }, 2000);

        } else {
            showToast("Error de Venta", res.message || "No se pudo procesar la venta en base de datos.", "danger");
            button.disabled = false;
            button.innerText = 'Confirmar Cobro';
        }
    })
    .catch(err => {
        console.error("Error en cobro AJAX:", err);
        showToast("Error de Red", "Ocurrió un error de red al procesar el cobro.", "danger");
        button.disabled = false;
        button.innerText = 'Confirmar Cobro';
    });
}