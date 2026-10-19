import qrcode
import qrcode.image.svg
from pathlib import Path
from ..config import ASSETS_QR_DIR

def generate_student_qr(roll_number: str) -> str:
    """
    Generates high-resolution PNG and crisp SVG QR codes for a student's roll number.
    Returns the relative path formatted for web serving.
    """
    clean_roll = roll_number.strip().upper()
    
    # 1. Standard PNG QR Code
    qr_png = qrcode.QRCode(
        version=1,
        error_correction=qrcode.constants.ERROR_CORRECT_H,
        box_size=10,
        border=3,
    )
    qr_png.add_data(clean_roll)
    qr_png.make(fit=True)
    img_png = qr_png.make_image(fill_color="black", back_color="white")
    
    png_filename = f"{clean_roll}.png"
    png_filepath = ASSETS_QR_DIR / png_filename
    img_png.save(str(png_filepath))
    
    # 2. Vector SVG QR Code (for crystal-clear printing)
    factory = qrcode.image.svg.SvgPathImage
    svg_img = qrcode.make(clean_roll, image_factory=factory)
    svg_filename = f"{clean_roll}.svg"
    svg_filepath = ASSETS_QR_DIR / svg_filename
    svg_img.save(str(svg_filepath))
    
    # Return standard relative path for database storage and web display
    return f"assets/qr/{png_filename}"
